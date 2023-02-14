<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;

class SpecialLinkLoginPages extends SpecialPage {
	function __construct() {
		parent::__construct( 'LinkLoginPages', 'loginlogs' );
	}


	function execute( $par ) {
		$this->setHeaders();

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
		$categories = LinkLogin::getLinkLoginCategories();
		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		$output->addHTML('<th>' . wfMessage("linklogin-categories")->text() . '</th>');
		$output->addHTML('</tr>');
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
		$groups = LinkLogin::getLinkLoginGroupsByCategory($par);

		//get Pages
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'cl_to' => $par
		];
		$pages = $dbr->select(
			['categorylinks','page'],
			['page_title','page_id'],
			$conds,
			__METHOD__,
			[],
			[
				'categorylinks' => [ 'INNER JOIN', [ 'cl_from=page_id'] ]
			]
		) ?: [];

		$output->addHTML('<container id="linklogin-body">');
		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		$output->addHTML('<th>' . wfMessage("linklogin-page")->text() . '</th>');
		$output->addHTML('<th>' . wfMessage("linklogin-user")->text() . '</th>');
		$output->addHTML('</tr>');

		//get Users corresponding to pages 
		foreach( $pages as $page ) {
			$dbr = $lb->getConnectionRef( DB_REPLICA );
			$conds = [
				'page_title' => $page->page_title
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

			$output->addHTML('<tr id=' . $page->page_id . '>');
			$output->addHTML('<td id="pagetitle">' . $page->page_title . '</td>');

			if( !empty( $user ) ) {
				$output->addHTML('<td id=' . $page->page_id . 'User' . '>');
				$output->addHTML('<span>' . $user . '</span>' . " ");
				$output->addHTML('<a href="#"><i class="fa fa-pen edit"></i></a>');
				$output->addHTML('<a href="#" class="unlink users" style="float:right">' . '&times;' . '</a>');
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
				//User Column
				$output->addHTML('<td id=' . $page->page_id . 'User' . '>');
				$output->addHTML('<container id='. $page->page_id . 'Fragment>');
				$output->addHTML('<div class="dropdown">');
				$output->addHTML('<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">');
				$output->addHTML(wfMessage("linklogin-assign-user")->text());
				$output->addHTML('</button>');
				$output->addHTML('<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">');
				foreach( $users as $user ){
					$output->addHTML('<a href="#" class="dropdown-item user">' . $user . '</a>');
				}
				$output->addHTML('</div>');

				//Neuen User anlegen
				$output->addHTML('<form class="user-create" novalidate>');
				$output->addHTML('<label for="username">'. wfMessage("linklogin-user-create-long")->text() . '</label>');
				$output->addHTML('<input id="' . $page->page_id .'Inputfield" class="username md-textarea form-control" rows="1">');
				$output->addHTML('<button type="button" class="btn btn-primary create" style="margin:5px 10px 0px 0px">' . wfMessage("linklogin-user-create-short")->text() . '</button>');
				$output->addHTML('<small id="' . $page->page_id . 'userError" class="userError text-danger"></small>');
				$output->addHTML('</form>');
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
