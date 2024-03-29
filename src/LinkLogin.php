<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use MailAddress;

/**
 * A wrapper class for the hooks of this extension.
 */
class LinkLogin {

	/**
	 * Populate the user_email_token field with a random md5 hash for all users
	 * meeting the conditions
	 * 
	 * @return Integer Number of populated rows
	 */
	public static function populateLoginTokens($par = NULL) {
		$groups = self::getLinkLoginGroups();

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );

		$conds = [ 
			'user_email_token_expires' => null,
			'user_email' => '',
			'ug_group' => $groups,
		];

		if($par) {
			$conds['user_id'] = (int)$par; 
		} else {
			$conds[] = "(TRIM('\0' FROM user_email_token)='' OR user_email_token is null)";
		}

		$users = $dbr->newSelectQueryBuilder()
			->select( 'user_id' )
			->from( 'user' )
			->join( 'user_groups', null, 'user_id=ug_user' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchFieldValues();

		if( count( $users ) > 0 ) {
			$dbw = $lb->getConnectionRef( DB_PRIMARY );
			$set = [ 'user_email_token = MD5( rand() )' ];
			$conds = [ 
				'user_id' => $users,
			];
			$res = $dbw->update( 
				'user',
				$set, 
				$conds, 
				__METHOD__
			);

			return $dbw->affectedRows();
		} else {
			return 0;
		}
	}


	/**
	 * Force the creation of a new login token for a specific user
	 * 
	 * remove email, email_confirmed and password?
	 * set user_email_token_expires to null
	 * create token and save it
	 * 
	 * @return String Token
	 * 
	 * @todo implement
	 */
	public static function createLoginToken( $user ) {
		die('not yet implemented');

		$token = 'created';

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$dbw->update( 'user',
			[ /* SET */
				'user_email_token = MD5( rand() )',
			], [ /* WHERE */
				"TRIM('\0' FROM user_email_token)=''",
				'user_email_token_expires' => null,
			], __METHOD__
		);
		return $token;
	}


	/**
	 * Get the corresponding user for a token
	 * 
	 * Conditions:
	 * - matching token
	 * - user_email_token_expires is null
	 * - user_email is empty
	 * - user is in on of the LinkLoginGroups
	 * 
	 * @return Integer User ID
	 */
	public static function getUserFromToken( $token ) {
		$groups = self::getLinkLoginGroups();

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$userId = $dbr->newSelectQueryBuilder()
			->select( 'user_id' )
			->from( 'user' )
			->join( 'user_groups', null, 'user_id=ug_user' )
			->where([
				'user_email' => '',
				'user_email_token' => $token,
				'user_email_token_expires' => null,
				'ug_group' => $groups,
			])
			->fetchField();

		return $userId;
	}


	/**
	 * Check, if a user is in one of the LinkLogin groups
	 * 
	 * @param Integer $user ID of the user to check
	 * @param Array $groups specify link login groups to use
	 * 
	 * @return Boolean
	 */
	public static function isLinkLoginUser($user, $groups = false) {
		$groupUsers = self::getLinkLoginGroupUsers($groups);

		return in_array($user, $groupUsers);
	}


	/**
	 * Get a list of all users who can login with a link, i.e. they
	 * - are in one of the LinkLogin groups
	 * - have a non empty user_email_token field and user_email_token_expires set to null
	 * 
	 * @param Array $groups specify link login groups to use
	 * 
	 * @return Wikimedia\Rdbms\ResultWrapper|Array Query Result with user_name and user_email_token
	 */
	public static function getLinkLoginUsers($groups = false) {
		$groupUsers = self::getLinkLoginGroupUsers($groups);	

		if( count( $groupUsers ) == 0 ) {
			return [];
		}

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$LinkLoginUsers = $dbr->newSelectQueryBuilder()
			->select( [ 
				'user_name', 
				'user_email_token', 
				'user_id' 
			] )
			->from( 'user' )
			->where([
				'user_id' => $groupUsers,
				'user_email' => '',
				"(TRIM('\0' FROM user_email_token)!='' OR not user_email_token is null)",
				'user_email_token_expires' => null,
			])
			->orderBy( 'user_name' )
			->distinct()
			->caller( __METHOD__ )
			->fetchResultSet() ?: [];

		return $LinkLoginUsers;
	}


	/**
	 * Get a List of all users that are in one of the LinkLogin groups
	 * 
	 * Code inspired by User::findUsersByGroup()
	 * 
	 * @param Array $groups specify link login groups to use
	 * 
	 * @return Array User IDs
	 */
	public static function getLinkLoginGroupUsers($groups = false) {
		if( !$groups ) {
			$groups = self::getLinkLoginGroups() ;
		} else {
			$groups = array_intersect( self::getLinkLoginGroups(), (array) $groups );
		}

		if( count($groups) == 0 ) {
			return [];
		}

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$groupUsers = $dbr->newSelectQueryBuilder()
			->select( 'ug_user' )
			->from( 'user_groups' )
			->where( ['ug_group' => $groups] )
			->orderBy( 'ug_user' )
			->distinct()
			->caller( __METHOD__ )
			->fetchFieldValues() ?: [];

		return $groupUsers;
	}


	/**
	 * Get a list of all user groups 
	 * 
	 * @param Boolean $category set true to return only groups with a category field
	 * 
	 * @return Array groups
	 */
	public static function getLinkLoginGroups($onlyWithCategory = false) {
		$all_groups = $GLOBALS['wgLinkLoginGroups'];
		$groups = [];
		foreach( $all_groups as $key => $group ) {
			if( is_array($group) ) {
				$groups[] = $key;
			} elseif( $onlyWithCategory == false) {
				$groups[] = $group;
			}
		}
		return $groups;
	}


	/**
	 * Get a list of all user groups of a certain Category
	 * 
	 * @param Boolean $category return only groups this category
	 * 
	 * @return Array groups
	 */
	public static function getLinkLoginGroupsByCategory($category = null) {
		$all_groups = $GLOBALS['wgLinkLoginGroups'];
		$groups = [];
		foreach( $all_groups as $group => $group_def ) {
			if( isset( $group_def['categories'] ) && in_array( $category, array_map( 'ucfirst', $group_def['categories'] ) ) ) {
				$groups[] = $group;
			}
		}
		return $groups;
	}


	/**
	 * Get a list of all categories (for all or specified groups) 
	 *
	 * @param Array $filter List of groups that should be considered
	 * 
	 * @return Array categories
	 */
	public static function getLinkLoginCategories($filter = null) {
		$groups = $GLOBALS['wgLinkLoginGroups'];
		if( !is_null( $filter ) ) {
			$groups = array_intersect_key( $groups, array_flip( $filter ) );
		}
		$categories = [];
		foreach( $groups as $group ) {
			if( isset( $group['categories'] ) ) {
				foreach( $group['categories'] as $category ) {
					$categories[] = ucfirst( $category ); 
				}
			}
		}
		return $categories;
	}


	/**
	 * Get a list of all categories associated to a user's group
	 *
	 * @param User $user
	 *
	 * @return Array categories
	 */
	public static function getLinkLoginCategoriesForUser( $user ) {
		$ugm = MediaWikiServices::getInstance()->getUserGroupManager();
		$usergroups = $ugm->getUserGroups($user);
		$categories = LinkLogin::getLinkLoginCategories( $usergroups );
		return $categories;
	}


	/**
	 * Check if a category has a filter defined, if yes return the filter
	 * 
	 * @param Array $category specify link login category to use
	 * 
	 * @return String filter | ''
	 */
	public static function getLinkLoginCategoryFilter($category) {
		return $GLOBALS['wgLinkLoginCategories'][lcfirst($category)]['filter'] ?? $GLOBALS['wgLinkLoginCategories'][ucfirst($category)]['filter'] ?? '';
	}


	/**
	 * Check if a group has a loginpage defined, if yes return the pagename
	 * 
	 * @param Integer $user ID of the user to check
	 * @param Array $groups specify link login groups to use
	 * 
	 * @return String loginpage | Null
	 */
	public static function getLoginpage($group) {
		$groups = $GLOBALS['wgLinkLoginGroups'];
		if( array_key_exists($group,$groups) ) {
			if( isset($groups[$group]['loginpage']) ) {
				$loginpage = $groups[$group]['loginpage'];
				return $loginpage;
			}
		}
		return null;
	}


	/**
	 * Get a list of all pages linked to a user
	 *
	 * There should be an entry in the ll_mapping_user table but the page should also be
	 * in one of the specified categories (e.g. of the group shown or that are associated 
	 * to one of the user's groups).
	 *
	 * @param String $user_id ID of the user
	 * @param Array $categories List of categories
	 *
	 * @return Array of pages
	 */
	public static function getPagesForUser( $user_id, $categories ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$pages = $dbr->newSelectQueryBuilder()
			->select( ['page_title', 'page_id'] )
			->from( 'll_mapping' )
			->join('user', null, 'user_id=ll_mapping_user')
			->join('page', null, 'page_id=ll_mapping_page')
			->join('categorylinks', null, 'cl_from=page_id')
			->where([
				'll_mapping_user' => $user_id,
				'cl_to' => $categories
			])
			->caller( __METHOD__)
			->fetchResultSet() ?: [];

		return $pages;
	}


	/**
	 * Log successfull login
	 * 
	 * @param Integer $user User ID
	 * @param String $hash Hash
	 */
	public static function logLinkLogin($user, $hash) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$res = $dbw->insert( 
			'll_loginlog',
			[
				'll_loginlog_user' => $user,
				'll_loginlog_hash' => $hash,
				'll_loginlog_timestamp' => time(),
			]);
	}


	/**
	 * Log unsuccessfull login attempt
	 * 
	 * @param String $ip User's IP address
	 * @param String $hash Hash
	 */
	public static function logLinkLoginAttempt($ip, $hash) {
		$time = time();
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$res = $dbw->insert( 
			'll_attemptlog',
			[
				'll_attemptlog_ip' => $ip,
				'll_attemptlog_hash' => $hash,
				'll_attemptlog_timestamp' => $time,
			]);	
		$id = $dbw->insertId();
		$threshold = $GLOBALS['wgLinkLoginAttemptlogThreshold'];

		//Check if $wgLinkLoginAttemptlogNotify is set
		if ($GLOBALS['wgLinkLoginAttemptlogNotify']) {
			$recipient = [new MailAddress($GLOBALS['wgLinkLoginAttemptlogNotify'])];

			//Check if $wgLinkLoginAttemptlogThreshold is met
			if ($id % $threshold == 0) {
				$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
				$dbr = $lb->getConnectionRef( DB_REPLICA );
				$res = $dbr->newSelectQueryBuilder()
					->select( ['ll_attemptlog_timestamp','ll_attemptlog_notification'] )
					->from( 'll_attemptlog' )
					->where([
						'MOD(ll_attemptlog_id,' . $threshold . ') = 0'
					])
					->orderBy('ll_attemptlog_id DESC')
					->caller( __METHOD__)
					->fetchResultSet();
				$attempts = [];
				foreach ($res as $row) {
					$attempts[] = [$row->ll_attemptlog_timestamp,$row->ll_attemptlog_notification];
				}

				//Check if a Message has been sent in a set period amount of time
				$pause = $GLOBALS['wgLinkLoginAttemptlogPause'];
				$send = true;
				foreach ($attempts as $attempt) {
					if ($attempt[1] == true){
						$last_notification = $attempt[0];
						$diff = $time - $last_notification;
						if ($diff < $pause){
							$send = false;
						}
						continue;
					}
				}
				//Send Notification if no Message has already been sent in a set period amount of time
				if ($send == true) { 
					$to = $recipient;
					$from = new MailAddress( $GLOBALS['wgPasswordSender'], wfMessage('Emailsender')->text() );
					$subject = trim( wfMessage('linklogin-attemptlog-notify-subject')->text() );
					$bodyText = wfMessage('linklogin-attemptlog-notify-body')->text();

					$emailer = MediaWikiServices::getInstance()->getEmailer();
					$status = $emailer->send(
						$to,
						$from,
						$subject,
						$bodyText,
					);

					if( !$status->ok ) {
						$errors = [];
						foreach( $status->errors as $error ) {
							$errors[] = wfMessage( $error['message'], $error['params'] )->text();
						}
						die(var_dump( $errors ) );
					} else {
						$dbw = $lb->getConnectionRef( DB_PRIMARY );
						$res = $dbw->update( 
							'll_attemptlog',
							['ll_attemptlog_notification' => true],
							['ll_attemptlog_id' => $id],
						);
					}
				}
			}	
		}
	}
}
