<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use DatabaseUpdater;
use Parser;
use OutputPage;
use Skin;
use SMWQueryProcessor;

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

			// add preference email to the top of the list if it hasn't been set at a custom position
			if( !isset( $preferences['email'] ) ) {
				$preferences = array_merge( ['email' => [ 'type' => 'email' ] ], $preferences );
			}

			// should the preference only be shown for users in specific groups?
			foreach( $preferences as $key => $preference ) {
				if( isset($preference['groups']) ) {
					$ugm = MediaWikiServices::getInstance()->getUserGroupManager();
					$usergroups = $ugm->getUserGroups($user);
					foreach( (array) $preference['groups'] as $group ) {
						if( in_array( $group, $usergroups ) ) {
							continue 2;
						} 
					}
					unset( $preferences[$key] );
				}
			}

			// should the preference only be shown if logged-in user is in specific groups?
			foreach( $preferences as $key => $preference ) {
				if( isset($preference['restricted']) ) {
					$current_user = \RequestContext::getMain()->getUser();
					$ugm = MediaWikiServices::getInstance()->getUserGroupManager();
					$current_usergroups = $ugm->getUserGroups($current_user);
					foreach( (array) $preference['restricted'] as $restricted ) {
						if( in_array( $restricted, $current_usergroups ) ) {
							continue 2;
						} 
					}
					unset( $preferences[$key] );
				}
			}

			foreach( $preferences as $key => $preference ) {
				// set default type text
				if( !isset( $preferences[$key]['type'] ) ) {
					$preferences[$key]['type'] = 'text';
				}

				// use label message if it exists or the preference key as default label
				if( wfMessage('linklogin-pref-' . $key)->exists() ) {
					$preferences[$key]['label-message'] = 'linklogin-pref-' . $key;
				} else {
					$preferences[$key]['label'] = ucfirst( $key );
				}

				// set default section
				if( !isset( $preferences[$key]['section'] ) ) {
					$preferences[$key]['section'] = wfMessage('linklogin-pref-section-key')->text();
				}
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

		$updater->addExtensionField(
			'll_attemptlog',
			'll_attemptlog_notification',
			__DIR__ . '/../sql/notification.sql'
		);

		$updater->addExtensionTable(
			'll_mapping',
			__DIR__ . '/../sql/mapping.sql'
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
		$parser->setFunctionHook( 'linklogin-recipients', [ self::class, 'renderLinkLoginRecipients' ] );
		$parser->setFunctionHook( 'linklogin-pref', [ self::class, 'renderLinkLoginPref' ] );
		$parser->setFunctionHook( 'linklogin-ifuser', [ self::class, 'renderIfUser' ] );
		$parser->setFunctionHook( 'linklogin-pages', [ self::class, 'renderPages' ] );
		$parser->setFunctionHook( 'linklogin-users', [ self::class, 'renderUsers' ] );
	}


	/**
	 * Allow editing for linkLoginUsers only for linked pages or namespaces explicitly set as editable
	 *
	 * @param Title $title
	 * @param User $user
	 * @param String $action
	 * @param Array $result
	 *
	 * @return Boolean
	 */
	public static function ongetUserPermissionsErrors( $title, $user, $action, &$result ) {
		$ns = $title->getNamespace();
		if( isset( $GLOBALS['wgLinkLoginEditableNamespaces'][$ns] ) && $GLOBALS['wgLinkLoginEditableNamespaces'][$ns] ) {
			return true;
		}
		$linkLoginUser = LinkLogin::isLinkLoginUser( $user->getId() );
		if( $linkLoginUser && $action == 'edit' ) {
			if( !$title->canExist() ) {
				return false;
			}
			$titleId = $title->getId();
			$categories = LinkLogin::getLinkLoginCategoriesForUser( $user );
			$pages = LinkLogin::getPagesForUser( $user->getId(), $categories );
			foreach( $pages as $page ) {
				if( $page->page_id == $titleId ) {
					return true;
				}
			}
			$result = ['linklogin-noedit'];
			return false;
		}
	}


	/**
	 * Add body class 'linklogin-user' if current user is LinkLogin user
	 *
	 * @return void
	 */
	public static function onSkinTweekiAdditionalBodyClasses( $skin, &$additionalBodyClasses ) {
		if( LinkLogin::isLinkLoginUser( $skin->getUser()->getId() ) ) {
			$additionalBodyClasses[] = 'linklogin-user';
		}
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
	static function renderLinkLoginRecipients( Parser $parser ) {
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
		$users = $dbr->newSelectQueryBuilder()
			->select('user_name')
			->from('user')
			->join('ll_mailinglog', null, 'user_id=ll_mailinglog_user')
			->where($conds)
			->caller(__METHOD__)
			->fetchFieldValues();
		
		$output = join($delimiter, $users);
		return $output;
	}


	/**
	 * Return first parameter, if user is a LinkLogin user or the second parameter if not
	 */
	static function renderIfUser( Parser $parser, $true, $false ) {
		$user = \RequestContext::getMain()->getUser();
		$linkLoginUsers = LinkLogin::isLinkLoginUser( $user->getId() );
		
		return $linkLoginUsers ? $true : $false;
	}


	/**
	 * Return a list of all pages linked to a user
	 *
	 * @param String $separator Separator for the list
	 *
	 * @return List of pages
	 */
	static function renderPages( Parser $parser ) {
		$options = self::extractOptions( array_slice( func_get_args(), 1 ) );

		if( isset( $options['user'] ) ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $options['user'] );
		} else {
			$user = \RequestContext::getMain()->getUser();
		}
		if( ! $user instanceof \MediaWiki\User\UserIdentity ) {
			return '';
		}
		$categories = LinkLogin::getLinkLoginCategoriesForUser( $user );
		if( empty( $categories ) ) {
			return '';
		}
		$pages = LinkLogin::getPagesForUser( $user->getId(), $categories );
		$title = [];
		foreach( $pages as $page ) {
			$titles[] = str_replace( '_', ' ', $page->page_title );
		}
		$separator = $options['sep'] ?? ',';
		return join( $separator, $titles );
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
			if ($option == "") {
				continue;
			}
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


	/**
	 * Parser function {{#linklogin-pref:}}
	 * 
	 * Return list of Users with specific options. Parameters:
	 * - option: WHERE Useroption is set and NOT empty
	 * - option=false: WHERE Useroption is NOT set or empty
	 * - option=value: WHERE Useroption is equal to value
	 * 
	 * @param Parser $parser Parser
	 * 
	 * @return comma separated list of user names
	 */
	static function renderLinkLoginPref( Parser $parser ) {
		$options = self::extractOptions( array_slice( func_get_args(), 1 ) );
		if (count($options) == 0) {
			return "option must be set";
		}

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$delimiter = $GLOBALS['wgLinkLoginDelimiter'];

		$options = (array) $options;

		$null = stripslashes("\'\'");

		foreach ($options as $key => $option){
			// set and not empty
			if ($option === true){
				$conds = 'up_property = ' . '"' . $key . '"' . ' AND up_value != X' . $null;
				$users = $dbr->newSelectQueryBuilder()
					->select('user_name')
					->from('user_properties')
					->join( 'user', NULL, 'up_user=user_id')
					->where($conds)
					->caller(__METHOD__)
					->fetchFieldValues();
				$user_array[] = $users;

			// not set or empty
			} elseif ($option == "false") {
				$conds = 'user_name NOT IN ' . '(SELECT user_name FROM user_properties JOIN `user` ON user_properties.up_user=user.user_id WHERE up_property =' . '"' . $key  . '" AND up_value != X' . $null . ')
				';
				$conds = 'user_name NOT IN (' . $dbr->newSelectQueryBuilder()
					->select('user_name')
					->from('user_properties')
					->join('user', NULL, 'up_user=user_id')
					->where('up_property = ' . '"' . $key . '"' . ' AND up_value != X' . $null)
					->getSQL() . ')';
				$users = $dbr->newSelectQueryBuilder()
					->select('user_name')
					->from('user_properties')
					->join( 'user', NULL, 'up_user=user_id')
					->where($conds)
					->caller(__METHOD__)
					->fetchFieldValues();
				$user_array[] = $users;

			// having a specific value
			} else {
				$conds = 'up_property = ' . '"' . $key . '"' . ' AND up_value = ' .  '"' . $option . '"';
				$users = $dbr->newSelectQueryBuilder()
					->select('user_name')
					->from('user_properties')
					->join( 'user', NULL, 'up_user=user_id')
					->where($conds)
					->caller(__METHOD__)
					->fetchFieldValues();
				$user_array[] = $users;
			}
		}

		$users = [];
		if (count($user_array) > 1){
			$users = call_user_func_array('array_intersect',$user_array);
		} elseif (count($user_array) == 1) {
			$users = $user_array[0];
		}
		$users = array_unique($users);
		$output = join($delimiter, $users);
		return $output;
	}

	/**
	 * Parser function {{#linklogin-users:}}
	 * 
	 * Return list of Users mapped to queried sites. Parameters:
	 * - filter: Only return users with this filter
	 * - group (optional): Only return users of this group
	 *
	 * @param Parser $parser Parser
	 * 
	 * @return List of Users
	 */
	static function renderUsers( Parser $parser ) {
		$options = self::extractOptions( array_slice( func_get_args(), 1 ) );

		if( isset($options['group']) ) {
			if( !in_array($options['group'], LinkLogin::getLinkLoginGroups() ) ) {
				return "";
			}
		}

		if( !isset($options['filter']) ) {
			return "Filter must be set";
		}

		//SMW Query to get all pages for the specific filter	
		$params = [
			$options['filter'],
			'format=array',
			'link=none',
			'sep=<SEP>',
			'propsep=<PROP>',
		];
		list( $query, $processed_params ) = SMWQueryProcessor::getQueryAndParamsFromFunctionParams( $params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE, false );
		$result = SMWQueryProcessor::getResultFromQuery( $query, $processed_params, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE );
		$result = str_replace(' ', '_', $result );
		$filtered = explode( '<SEP>', $result );

		//Get all users with the specific filter
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnection( DB_REPLICA );
		$users = $dbr->newSelectQueryBuilder()
  			->select('user_name')
			->from('user')
			->join('ll_mapping', null, 'll_mapping_user=user_id')
			->join('page', null, 'll_mapping_page=page_id')
			->where(['page_title' => $filtered])
			->caller( __METHOD__ );
		//filter users by group if set
		if( isset($options['group']) ) {
				$users = $users->join('user_groups', null, 'ug_user=user_id')
				->where(['ug_group' => $options['group'] ]);			
		}
		$users = $users->fetchFieldValues() ?: [];
		$users = array_unique($users);
		$delimiter = $GLOBALS['wgLinkLoginDelimiter'];
		$output = join($delimiter, $users);
		return $output;
	}

	/**
	 * Load LinkLogin Modules, i.e. scripts, on every Page
	 * 
	 * @param OutputPage $out
	 * @param Skin $skin
	 * 
	 * @return void
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$out->addModules( 'ext.linklogin' );
	}
}

