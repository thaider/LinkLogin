<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;

/**
 * A wrapper class for the hooks of this extension.
 */
class LinkLoginHooks {

	/**
	 * @param User $user
	 * @return void
	 */
	public static function onUserLoadAfterLoadFromSession( $user ) {
		$request = $user->getRequest();
		$token = $request->getVal('login');

		if( is_null( $token ) ) {
			return true;
		}

		$newUserId = LinkLogin::getUserFromToken( $token );

		if( !$newUserId ) {
			wfDebug( "LinkLogin: No matching user for login token" );
			return true;
		}

		$newUser = \User::newFromId( $newUserId );

		$user->setId( $newUserId );
		$user->loadFromId();
		$user->saveSettings();
		$user->setCookies();
		\Hooks::run( 'UserLoginComplete', [ &$user, "" ] );

		return true;
	}
}
