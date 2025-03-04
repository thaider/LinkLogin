<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SMWQueryProcessor;
use SpecialPage;

class SpecialLinkLoginUsers extends SpecialPage {
	function __construct() {
		parent::__construct( 'LinkLoginUsers', 'linklogin-link' );
	}


	function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions('linklogin-link');
		LinkLogin::populateLoginTokens();

		//Show Users and Pages belonging to the group set in $par
		if( isset( $par ) && $par != '' ) {
			if( in_array($par, LinkLogin::getLinkLoginGroups(true) ) ) {
				$this->showGroupDetails( $par );
				return true;
			}
		}

		$this->showOverview();
	}


	/**
	 * Show overview of all groups
	 * 
	 * @return void
	 */
	function showOverview() {
		$output = $this->getOutput();
		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');
		$groups = LinkLogin::getLinkLoginGroups(true);
		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		$output->addHTML('<th>' . wfMessage('linklogin-groups')->text() . '</th>');
		$output->addHTML('</tr>');
		$output->addHTML('<tr>');
		foreach( $groups as $group ) {
			$url = SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '/' . $group;
			if( wfMessage( 'group-' . $group )->exists() ) {
				$group = wfMessage( 'group-' . $group )->text();
			}
			$output->addHTML('<td>' . '<a href="' . $url . '">' . $group . '</a>' . '</td>');
			$output->addHTML('</tr>');
		}
		$output->addHTML('</table>');
	}


	/**
	 * Show Table of all Users and Pages belonging to a group
	 * 
	 * @return void
	 */
	function showGroupDetails($par) {
		$output = $this->getOutput();
		$output->addModules("ext.linklogin-mapping");
		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');

		$uom = MediaWikiServices::getInstance()->getUserOptionsManager();

		//get loginpage if set
		$loginpage = LinkLogin::getLoginpage($par);

		//get users
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$users = $dbr->newSelectQueryBuilder()
			->select( ['user_name', 'user_id', 'user_email_token'] )
			->from( 'user' )
			->join( 'user_groups', null, 'user_id=ug_user' )
			->where( [
				'ug_group' => $par
			] )
			->orderBy('user_name', 'ASC')
			->caller( __METHOD__ )
			->fetchResultSet() ?: [];
		


		//get display titles
		$categories = LinkLogin::getLinkLoginCategories([$par]);
		foreach( $categories as $category ) {
			$params = [
				'[[Category:' . $category . ']]',
				'?Display title of=',
				'format=array',
				'link=none',
				'sep=<SEP>',
				'propsep=<PROP>',
			];
			list( $query, $processed_params ) = SMWQueryProcessor::getQueryAndParamsFromFunctionParams( $params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE, false );
			$result = SMWQueryProcessor::getResultFromQuery( $query, $processed_params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE );
			$titles = explode( '<SEP>', $result );
			foreach( $titles as $title ) {
				if( !empty($title) ) {
					list( $title, $displaytitle ) = explode("<PROP>", $title );
				} else {
					$displaytitle = '';
				}
				if( $displaytitle == '' ) {
					$displaytitle = $title;
				}
				$displaytitles[$displaytitle] = $title;
			}
		
			//Filter Pages
			$filter = LinkLogin::getLinkLoginCategoryFilter($category);
			$params = [
				'[[Category:' . $category . ']]' . $filter,
				'?Display title of=',
				'format=array',
				'link=none',
				'sep=<SEP>',
				'propsep=<PROP>',
			];
			list( $query, $processed_params ) = SMWQueryProcessor::getQueryAndParamsFromFunctionParams( $params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE, false );
			$result = SMWQueryProcessor::getResultFromQuery( $query, $processed_params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE );
			$filtered = explode( '<SEP>', $result );
			foreach( $filtered as $filtered_title ) {
				if( !empty($filtered_title) ) {
					list( $title, $displaytitle ) = explode("<PROP>", $filtered_title );
				} 
				if( $displaytitle == '') {
					$displaytitle = $title;
				}
				if( $displaytitle != '') {
					$filtered_titles[] = $displaytitle;
				}
			}
		}

		$linked_pages = [];
		$used_pages = [];

		// get pages for every user in the group
		foreach( $users as $user ) {
			$pages = LinkLogin::getPagesForUser( $user->user_id, $categories );
			if( !empty($pages) ) {
				foreach($pages as $page){
					$page->displaytitle = array_search( str_replace( '_' ,' ', $page->page_title ), $displaytitles );
					$linked_pages[$user->user_name][$page->page_id]['title'] = $page->page_title;
					$linked_pages[$user->user_name][$page->page_id]['displaytitle'] = $page->displaytitle;
					$used_pages[] = $page->displaytitle;
				}
			}   
		}

		//get all pages belonging to the group's categories
		$unsorted_unlinked_pages = [];
		$original_titles = [];
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$pages = $dbr->newSelectQueryBuilder()
			->select( ['page_title', 'page_id'] )
			->from( 'categorylinks' )
			->join( 'page', null, 'cl_from=page_id' )
			->where( [
				'cl_to' => $categories
			] )
			->caller( __METHOD__ )
			->fetchResultSet() ?: [];

		if( !empty($pages) ) {
			foreach( $pages as $page ) {
				$page->displaytitle = array_search( str_replace( '_' ,' ', $page->page_title ), $displaytitles );
				$original_titles[$page->page_title ] = $page->displaytitle;
				$unsorted_unlinked_pages[$page->page_id]= $page->displaytitle;
			}
		}   

		foreach( $unsorted_unlinked_pages as $key => $unsorted_unlinked_page ) {
			if( in_array($unsorted_unlinked_page,$used_pages) ) {
				unset($unsorted_unlinked_pages[$key]);
			}
		}
		natcasesort($unsorted_unlinked_pages);

		$unlinked_pages = [];
		foreach($unsorted_unlinked_pages as $key => $unlinked_page) {
			$unlinked_pages[$key]['displaytitle'] = $unlinked_page;
			$unlinked_pages[$key]['title'] = array_search( $unlinked_page, $original_titles );
		}

		$assoc_categories = LinkLogin::getLinkLoginCategories([$par]);

		//get all preferences set in $wgLinkLoginPreferences
		$preferences = array_keys( $GLOBALS['wgLinkLoginPreferences'] );
		if( !in_array( 'email', $preferences ) ) {
			array_unshift( $preferences, 'email' );
		}

		//get all user_property values
		$user_ids = [];
		foreach( $users as $user ) {
			$user_ids[] = $user->user_id;
		}
		$user_properties = [];
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$user_properties_query = $dbr->newSelectQueryBuilder()
			->select( [
				'up_user',
				'up_property',
				'up_value'
			])
			->from( 'user_properties' )
			->where([ 
				'up_property' => $preferences,
				'up_user' => $user_ids,
				'up_value != ""',
			])
			->caller( __METHOD__ )
			->fetchResultSet();
				
		$properties = [];	
		//add user_properties to array in the order of preferences
		foreach( $preferences as $preference ) {
			foreach( $user_properties_query as $user_property ) {
				if( $preference == $user_property->up_property ) {
					$user_properties[$user_property->up_user][$user_property->up_property] = $user_property->up_value;
				}
			}
		}

		$old_par = $par;

		//check if a filter is set
		$query_filter = $this->getRequest()->getQueryValues();
		$filter_invert = false;
		$filter_noentries = false;
		if( isset( $query_filter['filter'] ) ) {
			if( $query_filter['filter'] == 'noentries' ) {
				$filter_noentries = true;
			}
			if( isset( $query_filter['invert'] ) ) {
				$filter_invert = true;
			}
			$query_filter = $query_filter['filter'];
		} else {
			$query_filter = false;
		}

		//if filter is set show only users where the filter value is not set or empty
		$filtered_users = [];
		if( $filter_noentries ) {
			foreach( $users as $user ) {
				if( !isset( $user_properties[$user->user_id] ) ) {
					$filtered_users[] = $user->user_id;
				}
			}
		} else {
			if( $filter_invert ) {
				//has not filter
				if( $query_filter ) {
					foreach( $users as $user ) {
						if( !isset( $user_properties[$user->user_id][$query_filter] ) ) {
							$filtered_users[] = $user->user_id;
						}
					}
				} 
			} else {
				//has filter
				if( $query_filter ){
					foreach( $users as $user ) {
						if( isset( $user_properties[$user->user_id][$query_filter] ) ) {
							$filtered_users[] = $user->user_id;
						}
					}
				}
			}
		}

		if( wfMessage( 'group-' . $par )->exists() ) {
			$par = wfMessage( 'group-' . $par )->text();
		}
		$output->setPageTitle( $this->getDescription() . ' (' . wfMessage('linklogin-group') . ': '  . $par . ')');

		$output->addHTML('<div class="col text-center"><a href="' . SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '"><button type="button" class="btn btn-secondary translate-middle">' . wfMessage('linklogin-overview') . '</button></a></div>');
		$output->addHTML('<div class="col" style="margin: 10px 0px">' . wfMessage("linklogin-associated") . ' ' . wfMessage("linklogin-categories"). ': ');
		foreach($assoc_categories as $assoc_cat){
			$url = SpecialPage::getTitleFor( 'LinkLoginPages' )->getLocalURL() . '/' . $assoc_cat;
			if( wfMessage( 'category-' . strtolower( $assoc_cat ) )->exists() ) {
				$assoc_cat = wfMessage( 'category-' . strtolower( $assoc_cat ) )->text();
			}
			$output->addHTML('<a href="' . $url . '">' . $assoc_cat . '</a>' . ' ');
		}
		$output->addHTML('</div>');

		$output->addHTML('<div class="col" style="margin: 10px 0px">' . wfMessage("linklogin-filter") . ': ');
		//toggle for has or has not user_property
		$output->addHTML('<div id="filter-toggle-buttongroup" class="btn-group btn-group-toggle me-2" data-bs-toggle="buttons">');
		$output->addHTML('<label id="has-button" class="btn btn-secondary btn-sm' . ($filter_noentries ? '">' : ($filter_invert ? '">' : ' active">')));
    	$output->addHTML('<input type="radio" name="options" id="option-has" autocomplete="off" checked>' . wfMessage("linklogin-filter-has") . '</label>');
  		$output->addHTML('<label id="has-not-button" class="btn btn-secondary btn-sm' . ($filter_invert ? ' active">' : '">'));
    	$output->addHTML('<input type="radio" name="options" id="option-has-not" autocomplete="off">' . wfMessage("linklogin-filter-has-not") . '</label>');
		$output->addHTML('<a href="' . SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '/' . $old_par . '?filter=noentries" id="has-nothing-button" class="btn btn-secondary btn-sm' . ($filter_noentries ? ' active"' : '">'));
    	$output->addHTML('<input type="radio" name="options" id="option-has-nothing" autocomplete="off">' . wfMessage("linklogin-filter-has-nothing") . '</a>');
		$output->addHTML('</div>');

		//dropdown for filtering by user_property
		$output->addHTML('<div class="btn-group">');
		$output->addHTML('<div class="dropdown">');
		$output->addHTML('<button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="user_properties" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . ($query_filter ? (wfMessage('linklogin-pref-' . $query_filter)->exists() ? wfMessage('linklogin-pref-' . $query_filter) : ucfirst($query_filter)) : wfMessage("linklogin-filter-property")) . '</button>');
		$output->addHTML('<div class="dropdown-menu" id="dropdown-menu-user-properties" aria-labelledby="user_properties">');
		foreach( $preferences as $preference ) {
			$url = SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '/' . $old_par . '?filter=' . $preference;
			$output->addHTML('<a class="dropdown-item user-property" data-preference="' . $preference . '" href="#">' . (wfMessage('linklogin-pref-' . $preference)->exists() ? wfMessage('linklogin-pref-' . $preference) : ucfirst($preference)) . '</a>');
		}
		$output->addHTML('</div>');
		$output->addHTML('</div>');
		$output->addHTML('</div>');

		if( $query_filter ) {
			$url = SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '/' . $old_par;
			$output->addHTML(' <a href="' . $url . '">' . wfMessage("linklogin-filter-delete") . '</a>');
		}
		$output->addHTML('</div>');
		$output->addHTML('</div>');
		$output->addHTML('<container id="linklogin-body">');
		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		$output->addHTML('<th>' . wfMessage("linklogin-username")->text() . '</th>');
		$output->addHTML('<th>' . wfMessage("linklogin-pages")->text() . '</th>');
		$output->addHTML('<th class="semorg-showedit"></th>');
		$output->addHTML('</tr>');

		foreach( $users as $user ) {
			if( !$query_filter || in_array( $user->user_id, $filtered_users ) ) {
				//Check if user has an e-mail associated to them
				$user_mail = \User::newFromId($user->user_id);
				$user->email = $uom->getOption( $user_mail, 'email');
				$user_name = str_replace(' ', '_', $user->user_name);
				$output->addHTML('<tr id=' . '"' . $user_name . '"' . '>');
				$output->addHTML('<td>' . '<span>' . $user->user_name . '</span>' . ' ' . '<a href="#"><i class="fa fa-pen edit" title="' . wfMessage('linklogin-edit-user') . '" data-bs-toggle="tooltip"></i></a>');
				$output->addHTML('<div class="linklogin-user-properties">');
				if( !isset( $user_properties[$user->user_id] ) ) {
					$user_properties[$user->user_id]['email'] = $user->email;
				}
				foreach($user_properties[$user->user_id] as $user_property => $property_value) {
					if( !empty( $property_value ) ) {
						$output->addHTML('<div class="linklogin-user-property">');
						$property_name = ucfirst( $user_property );
						if( wfMessage('linklogin-pref-' . $user_property)->exists() ) {
							$property_name = wfMessage('linklogin-pref-' . $user_property)->text();
						}
						$output->addHTML('<span class="linklogin-user-property-name">' . $property_name . ': ' . '</span>');
						$output->addHTML('<span class="linklogin-user-property-value">' . $property_value . '</span>');
						$output->addHTML('</div>');
					}
				}
				$output->addHTML('</div>');
				$output->addHTML('</td>');
				$output->addHTML('<td id="' . $user_name . 'Pages">');
				if( array_key_exists($user->user_name, $linked_pages)){
					$output->addHTML('<ul id="' . $user_name . 'List" class="ps-0">');
					foreach( $linked_pages[$user->user_name] as $id_key => $linked_page){
						if( in_array($linked_page['displaytitle'], $filtered_titles) ) {
							$output->addHTML('<li id="listitem-' . $id_key . '">');
							$output->addHTML('<span data-title="' . $linked_page['title'] . '"><a href="../' . htmlspecialchars($linked_page['title']) . '" target="_blank">' . $linked_page['displaytitle'] . '</a></span>');
							$output->addHTML('<a href="#" class="unlink pages ms-2"><i class="fa fa-times" title="' . wfMessage('linklogin-unlink') . '" data-bs-toggle="tooltip"></i></a>');
							$output->addHTML('</li>');
						}
					}
					$output->addHTML('</ul>');
				}
				$output->addHTML('<div class="dropdown">');
				$output->addHTML('<a class="dropdown-toggle pages" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">');
				$output->addHTML(wfMessage('linklogin-assign-page')->text());
				$output->addHTML('</a>');
				$output->addHTML('<div class="dropdown-menu pageslist" aria-labelledby="dropdownMenuButton">');
				foreach($unlinked_pages as $key => $unlinked_page){
					// show only pages not already associated with the user
					if(!in_array($unlinked_page['displaytitle'],$linked_pages)){
						//show only filtered pages
						if( in_array($unlinked_page['displaytitle'], $filtered_titles) ) {
							$output->addHTML('<a href="#" class="dropdown-item pages" id="dropdownitem-'. $key .'" data-title="' . $unlinked_page['title'] . '">' . $unlinked_page['displaytitle'] . '</a>');
						}
					}
				}
				$output->addHTML('</div></div>');
				$output->addHTML('</td>');

				//Add quick custom mail icons 
				$output->addHTML('<td class="semorg-showedit">');
				if( !is_null($loginpage) &&  !is_null($user->user_email_token)) {
					$link = $this->createCustomMailLink($loginpage,$user);
					$output->addHTML('<a id="' . $link . '" class="copy clipboard me-2" href="#" title="' . wfMessage('linklogin-clipboard')->text() . '" data-bs-toggle="tooltip"><i class="fa fa-clipboard"></i></a>');
					if( !empty($user->email) ){
						$encoded_link = urlencode($link);
						$output->addHTML('<a href="mailto:' . $user->email .'?body=' . $encoded_link . '"><i class="fa fa-envelope fa-sm" data-bs-toggle="tooltip" title="' . wfMessage('linklogin-mail-link')->text() . '"></i></a>');
					}
				}
				$output->addHTML('</td>');
				$output->addHTML('</tr>');
			}
		}
		$output->addHTML('</table>');
		$output->addHTML('</container>');
	}


	/**
	 * Create link to send custom mail
	 * 
	 * @param $mailing
	 * @param $recipient
	 * 
	 * @return String HTML for link
	 */
	function createCustomMailLink( $loginpage, $user ) {
		$title = \Title::newFromText($loginpage);
		$link = $title->getFullUrl([ 'login' => $user->user_email_token ]);
		return $link;
	}
} 
