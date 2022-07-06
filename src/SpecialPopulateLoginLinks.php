<?php

namespace MediaWiki\Extension\LinkLogin;
use SpecialPage;

class SpecialPopulateLoginLinks extends \SpecialPage {
	function __construct() {
		parent::__construct( 'PopulateLoginLinks', 'populateloginlinks' );
	}

	function execute( $par ) {
		$this->checkPermissions('populateloginlinks');

		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		
		if ($par) {
			$populatedCount = LinkLogin::populateLoginTokens($par);
			$users = LinkLogin::getLinkLoginUsers();
			foreach($users as $user) {
				if((int)$user->user_id == $par){
					$user_name = $user->user_name;
					continue;
				}
			}
			if($user_name == ""){
				$user_name = 'No User Found';
			}
			if($populatedCount == 1) {
				$output->addWikiMsg( 'linklogin-reset-success', $user_name );
			} elseif ($populatedCount == 0) {
				$output->addWikiMsg( 'linklogin-reset-fail', $user_name );
			}
		} else {
			$populatedCount = LinkLogin::populateLoginTokens();
			$output->addWikiMsg( 'linklogin-populated', $populatedCount );
			$list_heading = wfMessage('linklogin-list-heading')->text();
			$output->addWikiTextAsInterface( '===' . $list_heading . '===' );
			$groups = array_unique( (array)$GLOBALS['wgLinkLoginGroups'] );
			$groupUsers = LinkLogin::getLinkLoginGroupUsers();
			$output->addWikiMsg( 'linklogin-groupcount', count( $groupUsers ), join(', ', $groups) );
			$users = LinkLogin::getLinkLoginUsers();
			$special = SpecialPage::getTitleFor( 'PopulateLoginLinks' );
			$url = $special->getLocalURL();
			if( ( $usersCount = $users->numRows() ) > 0 ) {
				$usersTable = wfMessage('linklogin-conditioncount', $usersCount )->text();
				$usersTable .= '<table class="table table-bordered table-sm"><tr><th>Name</th><th>Hash</th><th class="semorg-showedit"></th></tr>';
				foreach( $users as $user ) {
					$usersTable .= '<tr><td>' . $user->user_name . '</td><td>' . $user->user_email_token . '</td><td class="semorg-showedit">' . '<a href="' . $special->getLocalURL() . '/' . $user->user_id . '"' . ' title="' . wfMessage('linklogin-reset')->text() . '" data-toggle="tooltip"' .  '><i class="fa fa-redo">' . '</i></a></td></tr>';

				}
				$usersTable .= '</table>';
			} else {
				$usersTable = 'no users';
			}
			$output->addHTML( $usersTable );
		}
	}
}