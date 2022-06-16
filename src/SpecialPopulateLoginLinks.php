<?php

namespace MediaWiki\Extension\LinkLogin;

class SpecialPopulateLoginLinks extends \SpecialPage {
	function __construct() {
		parent::__construct( 'PopulateLoginLinks', 'populateloginlinks' );
	}

	function execute( $par ) {
		$this->checkPermissions('populateloginlinks');

		$populatedCount = LinkLogin::populateLoginTokens();
		
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		$populatedFeedback = wfMessage('linklogin-populated', $populatedCount)->text();
		$output->addWikiTextAsInterface( $populatedFeedback );

		$list_heading = wfMessage('linklogin-list-heading')->text();
		$output->addWikiTextAsInterface( '===' . $list_heading . '===' );

		$groups = array_unique( (array)$GLOBALS['wgLinkLoginGroups'] );
		$groupUsers = LinkLogin::getLinkLoginGroupUsers();

		$output->addWikiMsg( 'linklogin-groupcount', count( $groupUsers ), join(', ', $groups) );

		$users = LinkLogin::getLinkLoginUsers();
		if( ( $usersCount = $users->numRows() ) > 0 ) {
			$usersTable = wfMessage('linklogin-conditioncount', $usersCount )->text();
			$usersTable .= '<table class="table table-bordered table-sm"><tr><th>Name</th><th>Hash</th></tr>';
			foreach( $users as $user ) {
				$usersTable .= '<tr><td>' . $user->user_name . '</td><td>' . $user->user_email_token . '</td></tr>';
			}
			$usersTable .= '</table>';
		} else {
			$usersTable = 'no users';
		}
		$output->addWikiTextAsInterface( $usersTable );
	}
}