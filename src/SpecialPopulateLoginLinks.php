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

		$populatedFeedback = '<code>user_email_token</code> fields for ' . $populatedCount . ' users have been populated.';
		$output->addWikiTextAsInterface( $populatedFeedback );


		$output->addWikiTextAsInterface( '=== List of all users with hashes ===' );

		$groups = array_unique( (array)$GLOBALS['wgLinkLoginGroups'] );
		$groupUsers = LinkLogin::getLinkLoginGroupUsers();

		$output->addWikiTextAsInterface( count( $groupUsers ) . ' users are in the groups defined by <code>$LinkLoginGroups</code> (' . join(', ', $groups) . ').');

		$users = LinkLogin::getLinkLoginUsers();
		if( count($users) > 0 ) {
			$usersTable = count($users) . ' of them meet all the conditions and have a hash defined:';
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