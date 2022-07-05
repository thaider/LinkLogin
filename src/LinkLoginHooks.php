<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use DatabaseUpdater;
use Parser;

/**
 * A wrapper class for the hooks of the LinkLogin extension.
 */
class LinkLoginHooks {

	/**
	 * Replace preferences with specified contact data if the user is a link login user
	 * 
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
	 * Try to log in user if the query parameter login was set
	 * 
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
			LinkLogin::logLinkLoginAttempt( $_SERVER['REMOTE_ADDR'], $token );
			return true;
		}

		// already logged in as that user
		if( $newUserId == $user->getId() ) {
			return true;
		}

		$newUser = \User::newFromId( $newUserId );

		// log in user
		$user->setId( $newUserId );
		$user->loadFromId();
		$user->saveSettings();
		$user->setCookies(null, null, true);
		\Hooks::run( 'UserLoginComplete', [ &$user, "" ] );

		LinkLogin::logLinkLogin( $newUserId, $token );

		return true;
	}


	/**
	 * Register database updates
	 * 
	 * @param DatabaseUpdate $updater
	 * 
	 * @return void
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'll_mailing',
			__DIR__ . '/../sql/mailing.sql'
		);

		$updater->addExtensionTable(
			'll_mailinglog',
			__DIR__ . '/../sql/mailinglog.sql'
		);

		$updater->addExtensionField(
			'll_mailing',
			'll_mailing_signature',
			__DIR__ . '/../sql/signature.sql'
		);

		$updater->addExtensionField(
			'll_mailing',
			'll_mailing_replyto',
			__DIR__ . '/../sql/replyto.sql'
		);

		$updater->addExtensionField(
			'll_mailing',
			'll_mailing_only',
			__DIR__ . '/../sql/only.sql'
		);

		$updater->addExtensionTable(
			'll_attemptlog',
			__DIR__ . '/../sql/attemptlog.sql'
		);

		$updater->addExtensionTable(
			'll_loginlog',
			__DIR__ . '/../sql/loginlog.sql'
		);

		$updater->addExtensionField(
			'll_mailing',
			'll_mailing_except',
			__DIR__ . '/../sql/except.sql'
		);

		$updater->addExtensionField(
			'll_mailing',
			'll_mailing_subjecttemplate',
			__DIR__ . '/../sql/subjecttemplate.sql'
		);

		$updater->addExtensionField(
			'll_mailing',
			'll_mailing_email',
			__DIR__ . '/../sql/email.sql'
		);
	}


	/**
	 * Register Parser Functions
	 * 
	 * @param Parser $parser Parser
	 * 
	 * @return void
	 */
	public static function onParserFirstCallInit( Parser $parser ){
		$parser->setFunctionHook( 'linklogin-recipients', [ self::class, 'renderLinkloginRecipients' ] );
	}


	/**
	 * Parser function {{#linklogin-recipients:}}
	 * 
	 * Return list of a mailing's recpients. Parameters:
	 * - mailing: Mailing ID
	 * - before: Timestamp
	 * - after: Timestamp
	 * 
	 * @param Parser $parser Parser
	 * 
	 * @return comma separated list of recipients' user names
	 */
	static function renderLinkloginRecipients( Parser $parser ) {
		$options = self::extractOptions( array_slice( func_get_args(), 1 ) );
		if (!isset($options['mailing'])) {
			return "Mailing must be set";
		}

		$delimiter = $GLOBALS['wgLinkLoginDelimiter'];
		
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [ 
			'll_mailinglog_mailing' => $options['mailing'],
		];
		if (isset($options['before'])) {
			$conds[] = 'll_mailinglog_timestamp' . '<=' . $options['before']; 
		}
		if (isset($options['after'])) {
			$conds[] = 'll_mailinglog_timestamp' . '>=' . $options['after']; 
		}
		$users = $dbr->selectFieldValues(
			[ 'user', 'll_mailinglog' ],
			'user_name', 
			$conds, 
			__METHOD__,
			[],
			[
				'user' => [ 'INNER JOIN', [ 'user_id=ll_mailinglog_user'] ]
			]
		);
		
		$output = join($delimiter, $users);
		return $output;
	}


	/**
	 * Converts an array of values in form [0] => "name=value"
	 * into a real associative array in form [name] => value
	 * If no = is provided, true is assumed like this: [name] => true
	 *
	 * @param array string $options
	 * 
	 * @return array $results
	 */
	static function extractOptions( array $options ) {
		$results = [];
		foreach ( $options as $option ) {
			$pair = array_map( 'trim', explode( '=', $option, 2 ) );
			if ( count( $pair ) === 2 ) {
				$results[ $pair[0] ] = $pair[1];
			}
			if ( count( $pair ) === 1 ) {
				$results[ $pair[0] ] = true;
			}
		}
		return $results;
	}
}
