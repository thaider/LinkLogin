<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SMWQueryProcessor;
use SpecialPage;

class SpecialLinkLoginPages extends SpecialPage {
	function __construct() {
		parent::__construct( 'LinkLoginPages', 'linklogin-link' );
	}


	function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions('linklogin-link');
		LinkLogin::populateLoginTokens();

		//Show Users and Pages belonging to the group set in $par
		if( isset( $par ) && $par != '' ) {
			if( in_array($par, LinkLogin::getLinkLoginCategories() ) ) {
				$this->showCategoryDetails( $par );
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
		$categories = LinkLogin::getLinkLoginCategories();
		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		$output->addHTML('<th>' . wfMessage("linklogin-categories")->text() . '</th>');
		$output->addHTML('</tr>');
		$categories = array_unique($categories);
		natcasesort($categories);
		foreach( $categories as $category ) {
			$output->addHTML('<tr>');
			$url = SpecialPage::getTitleFor( 'LinkLoginPages' )->getLocalURL() . '/' . $category;
			$output->addHTML('<td><a href="' . $url . '">' . $category . '</a></td>');
			$output->addHTML('</tr>');
		}
		$output->addHTML('</table>');
	}


	/**
	 * Show Table of all Pages and Users belonging to a Category
	 * 
	 * @return void
	 */
	function showCategoryDetails($par) {
		$output = $this->getOutput();
		$output->addModules("ext.linklogin-mapping");
		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');
		$api_access = false;
		if( MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight($this->getUser(),'linklogin-link') ) {
				$api_access = true;
		} 

		$groups = LinkLogin::getLinkLoginGroupsByCategory($par);

		//Get Displaytitles
		$filter = LinkLogin::getLinkLoginCategoryFilter($par);
		$params = [
			'[[Category:' . $par . ']]' . $filter, // die Abfragebedingungen (Query)
			'?Display title of=', // ein zusätzliches Attribut, das ausser dem Seitentitel ausgegeben werden soll
			'format=array', // das Ausgabeformat
			'headers=hide',
			'link=none', // der Seitentitel würde sonst als Link (in Wiki-Markup) ausgegeben
			'sep=<SEP>', // das Trennzeichen zwischen den Seiten
			'propsep=<PROP>', // das Trennzeichen zwischen den Attributen
		];

		list( $query, $processed_params ) = SMWQueryProcessor::getQueryAndParamsFromFunctionParams( $params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE, false );
		$result = SMWQueryProcessor::getResultFromQuery( $query, $processed_params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE );
		$pages = explode( '<SEP>', $result );
		$displaytitles = [];
		$displaytitle = '';
		foreach( $pages as $page ) {
			if( !empty($page) ) {
				list( $title, $displaytitle ) = explode("<PROP>", $page );
			} else {
				$title = '';
			}
			if( $displaytitle == '' ) {
				$displaytitle = $title;
			}
			$displaytitles[$title] = $displaytitle;
		}

		//get Pages
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'cl_to' => $par
		];
		$titles = $dbr->select(
			['categorylinks','page'],
			['page_title','page_id'],
			$conds,
			__METHOD__,
			[],
			[
				'categorylinks' => [ 'INNER JOIN', [ 'cl_from=page_id'] ]
			]
		) ?: [];

		$pages = [];
		foreach( $titles as $title ) {
			$page_title = str_replace( '_', ' ', $title->page_title);
			if( array_key_exists($page_title, $displaytitles)){
				$pages[] = (object) [
					'id' => $title->page_id,
					'title' => $title->page_title,
					'displaytitle' => $displaytitles[ $page_title ],
				];
			}
		}

		usort( $pages, function( $a, $b ) {
			return strnatcasecmp( $a->displaytitle, $b->displaytitle );
		} );

		$assoc_groups = LinkLogin::getLinkLoginGroupsByCategory($par);

		$output->setPageTitle( $this->getDescription() . ' (' . wfMessage('linklogin-category') . ': '  . $par . ')');
		$output->addHTML('<div class="col text-center"><a href="' . SpecialPage::getTitleFor( 'LinkLoginPages' )->getLocalURL() . '"><button type="button" class="btn btn-secondary translate-middle">' . wfMessage('linklogin-overview') . '</button></a></div>');
		$output->addHTML('<div class="col" style="margin: 10px 0px">' . wfMessage("linklogin-associated") . ' ' . wfMessage("linklogin-groups"). ': ');
		foreach($assoc_groups as $assoc_group){
			$url = SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '/' . $assoc_group;
			$output->addHTML('<a href="' . $url . '">' . $assoc_group . '</a>' . ' ');
		}
		$output->addHTML('</div>');
		$output->addHTML('<container id="linklogin-body">');
		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		$output->addHTML('<th>' . wfMessage("linklogin-page")->text() . '</th>');
		$output->addHTML('<th>' . wfMessage("linklogin-user")->text() . '</th>');
		$output->addHTML('</tr>');

		//get Users corresponding to pages 
		foreach( $pages as $page ) {
			$dbr = $lb->getConnectionRef( DB_REPLICA );
			$conds = [
				'page_title' => $page->title
			];
			$user = $dbr->selectField(
				['ll_mapping','page','user'],
				'user_name',
				$conds,
				__METHOD__,
				[],
				[            
					'll_mapping' => [ 'INNER JOIN', ['ll_mapping_page=page_id'] ],
					'user' =>  ['INNER JOIN', ['ll_mapping_user=user_id'] ]
				]
			) ?: [];

			$output->addHTML('<tr id=' . $page->id . '>');
			$output->addHTML('<td id="pagetitle">' . $page->displaytitle . '</td>');

			if( !empty( $user ) ) {
				$output->addHTML('<td id=' . $page->id . 'User' . '>');
				$output->addHTML('<span>' . $user . '</span>' . " ");
				if( $api_access ) {
					$output->addHTML('<a href="#"><i class="fa fa-pen edit" title="' . wfMessage('linklogin-edit-user') . '" data-toggle="tooltip"></i></a>');
					$output->addHTML('<a href="#" class="unlink users ml-2"><i class="fa fa-times" title="' . wfMessage('linklogin-unlink') . '" data-toggle="tooltip"></i></a>');
				}
				$output->addHTML('</td>');
			} else {             
				foreach( $groups as $group ) {
					$dbr = $lb->getConnectionRef( DB_REPLICA );
					$conds = [
						'ug_group' => $group
					];
					$users = $dbr->selectFieldValues(
						['user', 'user_groups'],
						'user_name',
						$conds,
						__METHOD__,
						[],
						[
							'user' => [ 'INNER JOIN', [ 'user_id=ug_user'] ]
						]
					) ?: [];
				}                
				sort($users);
				//User Column
				$output->addHTML('<td id=' . $page->id . 'User' . '>');
				$output->addHTML('<container id='. $page->id . 'Fragment>');
				if( $api_access ) {
					$output->addHTML('<div class="dropdown">');
					$output->addHTML('<a class="dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">');
					$output->addHTML(wfMessage("linklogin-assign-user")->text());
					$output->addHTML('</a>');
					$output->addHTML('<div class="dropdown-menu userlist" aria-labelledby="dropdownMenuButton">');
					foreach( $users as $user ){
						$output->addHTML('<a href="#" class="dropdown-item user">' . $user . '</a>');
					}
					$output->addHTML('</div>');
				}

				//Neuen User anlegen, wenn User 'createaccount' Rechte besitzt
				if( MediaWikiServices::getInstance()
					->getPermissionManager()
					->userHasRight($this->getUser(),
					'createaccount') ) 
				{
					$output->addHTML('<form class="user-create form-inline" novalidate>');
					$output->addHTML('<input id="' . $page->id .'Inputfield" class="username md-textarea form-control mr-1" rows="1" style="width:200px" placeholder="' . wfMessage("linklogin-user-create-placeholder")->text() . '">');
					$output->addHTML('<button type="submit" class="btn btn-primary create">' . wfMessage("linklogin-user-create-short")->text() . '</button>');
					$output->addHTML('<small id="' . $page->id . 'userError" class="userError text-danger"></small>');
					$output->addHTML('</form>');
				}
				$output->addHTML('</td>');
				$output->addHTML('</container>');
				$output->addHTML('</tr>');
			}
		}
		$output->addHTML('</table>');
		$output->addHTML('</container>');
		$output->addHTML('<p id="messageEmpty" hidden>' . wfMessage("linklogin-user-error-empty")->text() . '</p>');
		$output->addHTML('<p id="messageSpecial" hidden>' . wfMessage("linklogin-user-error-special")->text() . '</p>');
		$output->addHTML('<p id="messageExists" hidden>' . wfMessage("linklogin-user-error-exists")->text() . '</p>');
	}
}
