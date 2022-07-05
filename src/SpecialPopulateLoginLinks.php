<?php

namespace MediaWiki\Extension\LinkLogin;
use \MediaWiki\MediaWikiServices;
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
		
		var_dump($par);
		if( !is_null($par)){
			return;
		}

		if ($par) {
			$populatedCount = LinkLogin::populateLoginTokens($par);
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbr = $lb->getConnectionRef( DB_REPLICA );
			$res = $dbr->selectFieldValues( 'user', 'user_name', ['user_id' => (int)$par], __METHOD__, []);
			$name = $res[0];
			if($name == ""){
				$name = 'No User Found';
			}
			if($populatedCount == 1) {
				$output->addWikiMsg( 'linklogin-reset-success', $name );
			} elseif ($populatedCount == 0) {
				$output->addWikiMsg( 'linklogin-reset-fail', $name );
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
					$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
					$dbr = $lb->getConnectionRef( DB_REPLICA );
					$res = $dbr->selectFieldValues( 'user', 'user_id', ['user_name' => $user->user_name], __METHOD__, []);
					$userId = $res[0];
					$usersTable .= '<tr><td>' . $user->user_name . '</td><td>' . $user->user_email_token . '</td><td class="semorg-showedit">' . '<a href="' . $special->getLocalURL() . '/' . $userId . '"' . ' title="' . wfMessage('linklogin-reset')->text() . '" data-toggle="tooltip"' .  '><i class="fa fa-redo">' . '</i></a></td></tr>';

				}
				$usersTable .= '</table>';
			} else {
				$usersTable = 'no users';
			}
			$output->addHTML( $usersTable );
		}
	}
}