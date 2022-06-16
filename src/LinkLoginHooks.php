<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use DatabaseUpdater;

/**
 * A wrapper class for the hooks of the LinkLogin extension.
 */
class LinkLoginHooks {

	/**
	 * @param User $user
	 * @param Array $preferences
	 * 
	 * @return void
	 */
	public static function onGetPreferences( $user, &$preferences ) {
        $linkLoginUsers = LinkLogin::isLinkLoginUser( $user->getId() );

        if( $linkLoginUsers ) {
            $preferences = $GLOBALS['wgLinkLoginPreferences'];
            foreach( $preferences as $key => $preference ) {
            	if( !isset( $preferences[$key]['type'] ) ) {
            		$preferences[$key]['type'] = 'text';
            	}
            	if( wfMessage('linklogin-pref-' . $key)->exists() ) {
            		$preferences[$key]['label-message'] = 'linklogin-pref-' . $key;
            	} else {
            		$preferences[$key]['label'] = ucfirst( $key );
            	}
            	$preferences[$key]['section'] = 'personal';
            }

        	return false;
        }
	}


	/**
	 * @param User $user
	 * 
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
		$user->setCookies(null, null, true);
		\Hooks::run( 'UserLoginComplete', [ &$user, "" ] );

		return true;
	}


	/**
	 * @param DatabaseUpdate $updater
	 * 
	 * @return void
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'mailing',
			 __DIR__ . '/../sql/mailing.sql'
		);

		$updater->addExtensionTable(
			'mailinglog',
			 __DIR__ . '/../sql/mailinglog.sql'
		);
	}
}
