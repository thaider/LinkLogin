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

		$users = $dbr->selectFieldValues(
			[ 'user', 'user_groups' ],
			'user_id', 
			$conds, 
			__METHOD__,
			[],
			[
				'user' => [ 'INNER JOIN', [ 'user_id=ug_user'] ]
			]
		);

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
	 * force the creation of a new login token for a specific user
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
		$conds = [
			'user_email' => '',
			'user_email_token' => $token,
			'user_email_token_expires' => null,
			'ug_group' => $groups,
		];
		$userId = $dbr->selectField(
			[ 'user', 'user_groups' ],
			'user_id',
			$conds,
			__METHOD__,
			[],
			[
				'user' => [ 'INNER JOIN', [ 'user_id=ug_user'] ]
			]
		);

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
		$conds = [
			'user_id' => $groupUsers,
			'user_email' => '',
			"(TRIM('\0' FROM user_email_token)!='' OR not user_email_token is null)",
			'user_email_token_expires' => null,

		];
		$LinkLoginUsers = $dbr->select(
			'user',
			['user_name','user_email_token', 'user_id'],
			$conds,
			__METHOD__,
			[
				'DISTINCT' => true,
				'ORDER BY' => 'user_name'
			]
		) ?: [];
		
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
			$groups = array_intersect( self::getLinkLoginGroups(), $groups );
		}

		if( count($groups) == 0 ) {
			return [];
		}

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [ 
			'ug_group' => $groups 
		];
		$groupUsers = $dbr->selectFieldValues(
			'user_groups',
			'ug_user',
			$conds,
			__METHOD__,
			[
				'DISTINCT' => true,
				'ORDER BY' => 'ug_user'
			]
		) ?: [];
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
		$all_groups = array_unique( $GLOBALS['wgLinkLoginGroups'], SORT_REGULAR );
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
		$all_groups = array_unique( $GLOBALS['wgLinkLoginGroups'], SORT_REGULAR );
		$groups = [];
		foreach( $all_groups as $key => $group ) {
			if( is_array($group) ) {
				foreach( $group as $cat_key => $categories){
					if( is_array($categories) ) {
						if( in_array($category, $categories) ){
							$groups[] = $key;
						}
					} else if( $categories == $category){
						$groups[] = $key;
					}
				}
			}
		}
		return $groups;
	}


	/**
	 * Get a list of all categories 
	 * 
	 * @return Array categories
	 */
	public static function getLinkLoginCategories() {
		$groups = array_unique( $GLOBALS['wgLinkLoginGroups'], SORT_REGULAR );
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
	 * Get a list of all categories of a certain group
	 * 
	 * @return Array categories
	 */
	public static function getLinkLoginCategoriesByGroup($group = null) {
		$all_categories = array_unique( $GLOBALS['wgLinkLoginGroups'], SORT_REGULAR );
		$categories = [];
		foreach( $all_categories as $key => $group_categories ) {
			if( is_array($group_categories) ) {
				if( $key == $group){
					foreach( $group_categories as $group_category ) {
						foreach( $group_category as $category ) {
							$categories[] = $category; 
						}
					}
				}
			}
		}
		return $categories;
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
				$res = $dbr->select( 
					'll_attemptlog',
					['ll_attemptlog_timestamp','ll_attemptlog_notification'],
					['MOD(ll_attemptlog_id,' . $threshold . ') = 0'],
					__METHOD__, 
					['ORDER BY' => 'll_attemptlog_id DESC'],
				);	
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
