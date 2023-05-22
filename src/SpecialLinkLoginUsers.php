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
			->where( ['ug_group' => $par] )
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
					$linked_pages[$user->user_name][$page->page_id] = $page->displaytitle;
					$used_pages[] = $page->displaytitle;
				}
			}   
		}

		//get all pages belonging to the group's categories
		$unlinked_pages = [];
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$pages = $dbr->newSelectQueryBuilder()
			->select( ['page_title', 'page_id'] )
			->from( 'categorylinks' )
			->join( 'page', null, 'cl_from=page_id' )
			->where( ['cl_to' => $categories] )
			->caller( __METHOD__ )
			->fetchResultSet() ?: [];

		if( !empty($pages) ) {
			foreach( $pages as $page ) {
				$page->displaytitle = array_search( str_replace( '_' ,' ', $page->page_title ), $displaytitles );
				$unlinked_pages[$page->page_id] = $page->displaytitle;
			}
		}   

		foreach( $unlinked_pages as $key => $unlinked_page ) {
			if( in_array($unlinked_page,$used_pages) ) {
				unset($unlinked_pages[$key]);
			}
		}
		natcasesort($unlinked_pages);

		$assoc_categories = LinkLogin::getLinkLoginCategories([$par]);

		if( wfMessage( 'group-' . $par )->exists() ) {
			$par = wfMessage( 'group-' . $par )->text();
		}
		$output->setPageTitle( $this->getDescription() . ' (' . wfMessage('linklogin-group') . ': '  . $par . ')');

		$output->addHTML('<div class="col text-center"><a href="' . SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '"><button type="button" class="btn btn-secondary translate-middle">' . wfMessage('linklogin-overview') . '</button></a></div>');
		$output->addHTML('<div class="col" style="margin: 10px 0px">' . wfMessage("linklogin-associated") . ' ' . wfMessage("linklogin-categories"). ': ');
		foreach($assoc_categories as $assoc_cat){
			$url = SpecialPage::getTitleFor( 'LinkLoginPages' )->getLocalURL() . '/' . $assoc_cat;
			$output->addHTML('<a href="' . $url . '">' . $assoc_cat . '</a>' . ' ');
		}
		$output->addHTML('</div>');
		$output->addHTML('<container id="linklogin-body">');
		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		$output->addHTML('<th>' . wfMessage("linklogin-username")->text() . '</th>');
		$output->addHTML('<th>' . wfMessage("linklogin-pages")->text() . '</th>');
		$output->addHTML('<th class="semorg-showedit"></th>');
		$output->addHTML('</tr>');

		$preferences = array_keys( $GLOBALS['wgLinkLoginPreferences'] );
		if( !in_array( 'email', $preferences ) ) {
			array_unshift($preferences, 'email');
		}

		//get all user_property values
		$user_ids = [];
		foreach( $users as $user ) {
			$user_ids[] = $user->user_id;
		}
		$user_properties = [];
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$user_properties_query = $dbr->newSelectQueryBuilder()
			->select( [
				'up_user',
				'up_property',
				'up_value'
			])
			->from( 'user_properties' )
			->where(' up_user IN (' . implode(',', $user_ids) . ') ')
			->caller( __METHOD__ )
			->fetchResultSet();
		
		$user_properties = [];
		
		foreach($user_properties_query as $user_property) {
			if( $user_property->up_property != "VectorSkinVersion" ) {
				$user_properties[$user_property->up_user]['property'][] = $user_property->up_property;
				$user_properties[$user_property->up_user]['property_value'][] = $user_property->up_value;
			}
		}
		foreach( $users as $user ) {
			$user_name = str_replace(' ', '_', $user->user_name);
			$output->addHTML('<tr id=' . '"' . $user_name . '"' . '>');
			$output->addHTML('<td>' . '<span>' . $user->user_name . '</span>' . ' ' . '<a href="#"><i class="fa fa-pen edit" title="' . wfMessage('linklogin-edit-user') . '" data-toggle="tooltip"></i></a>');
			$output->addHTML('<div class="linklogin-user-properties">');
			if( !isset($user_properties[$user->user_id]) ) {
				$user_properties[$user->user_id]['property'][] = "email";
				$user_properties[$user->user_id]['property_value'][] = "";
			}
			foreach($user_properties[$user->user_id]['property'] as $key => $user_property) {
				$output->addHTML('<div class="linklogin-user-property">');
				if( wfMessage('linklogin-pref-' . $user_property)->text() != '⧼linklogin-pref-' . $user_property . '⧽') {
					$output->addHTML('<span class="linklogin-user-property-name">' . wfMessage('linklogin-pref-' . $user_property)->text() . ': ' . '</span>');
				} else {
					$output->addHTML('<span class="linklogin-user-property-name">' . ucfirst($user_property) . ': ' . '</span>');
				}
				$output->addHTML('<span class="linklogin-user-property-value">' . $user_properties[$user->user_id]["property_value"][$key] . '</span>');
				$output->addHTML('</div>');
			}
			$output->addHTML('</div>');
			$output->addHTML('</td>');
			$output->addHTML('<td id="' . $user_name . 'Pages">');
			if( array_key_exists($user->user_name, $linked_pages)){
				$output->addHTML('<ul id="' . $user_name . 'List">');
				foreach( $linked_pages[$user->user_name] as $id_key => $linked_page){
					if( in_array($linked_page, $filtered_titles) ) {
						$output->addHTML('<li id="listitem-' . $id_key . '">');
						$output->addHTML('<span>' . $linked_page . '</span>');
						$output->addHTML('<a href="#" class="unlink pages ml-2"><i class="fa fa-times" title="' . wfMessage('linklogin-unlink') . '" data-toggle="tooltip"></i></a>');
						$output->addHTML('</li>');
					}
				}
				$output->addHTML('</ul>');
			}
			$output->addHTML('<div class="dropdown">');
			$output->addHTML('<a class="dropdown-toggle pages" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">');
			$output->addHTML(wfMessage('linklogin-assign-page')->text());
			$output->addHTML('</a>');
			$output->addHTML('<div class="dropdown-menu pageslist" aria-labelledby="dropdownMenuButton">');
			foreach($unlinked_pages as $key => $unlinked_page){
				// show only pages not already associated with the user
				if(!in_array($unlinked_page,$linked_pages)){
					//show only filtered pages
					if( in_array($unlinked_page, $filtered_titles) ) {
						$output->addHTML('<a href="#" class="dropdown-item pages" id="dropdownitem-'. $key .'">' . $unlinked_page . '</a>');
					}
				}
			}
			$output->addHTML('</div></div>');
			$output->addHTML('</td>');

			//Look if User has an e-mail assoiciated to them
			$user_mail = \User::newFromId($user->user_id);
			foreach( $preferences as $preference ) {
				$user_mail->{$preference} = $uom->getOption( $user_mail, $preference );
			}
			if( isset($user_mail->email) ){
				$email = $user_mail->email;
			} else {
				$email = "";
			}

			//Add quick custom mail icons 
			$output->addHTML('<td class="semorg-showedit">');
			if( !is_null($loginpage) &&  !is_null($user->user_email_token)) {
				$link = $this->createCustomMailLink($loginpage,$user);
				$output->addHTML('<a id="' . $link . '" class="copy clipboard mr-2" href="#" title="' . wfMessage('linklogin-clipboard')->text() . '" data-toggle="tooltip"><i class="fa fa-clipboard"></i></a>');
				if( !empty($email) ){
					$encoded_link = urlencode($link);
					$output->addHTML('<a href="mailto:' . $email .'?body=' . $encoded_link . '"><i class="fa fa-envelope fa-sm" data-toggle="tooltip" title="' . wfMessage('linklogin-mail-link')->text() . '"></i></a>');
				}
			}
			$output->addHTML('</td>');
			$output->addHTML('</tr>');
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
