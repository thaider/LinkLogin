<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;

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
	public static function populateLoginTokens() {
		$groups = array_unique( (array)$GLOBALS['wgLinkLoginGroups'] );
		
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [ 
			//Erste Condition fällt weg
			"(TRIM('\0' FROM user_email_token)='' OR user_email_token is null)",
			'user_email_token_expires' => null,
			'user_email' => '',
			'ug_group' => $groups,
		];
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
		$groups = array_unique( (array)$GLOBALS['wgLinkLoginGroups'] );

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
	 * Check, if a user can login with a link, i.e. they
	 * - are in one of the LinkLogin groups
	 * - have a non empty user_email_token field and user_email_token_expires set to null
	 * 
	 * @param Integer $user ID of the user to check
	 * @param Array $groups specify link login groups to use
	 * 
	 * @return Wikimedia\Rdbms\ResultWrapper|Array Query Result with user_name and user_email_token
	 */
	public static function isLinkLoginUser($user, $groups = false) {
		$groupUsers = self::getLinkLoginGroupUsers($groups);	

		if( count( $groupUsers ) == 0 ) {
			return false;
		}

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'user_id' => $user,
			'user_email' => '',
			"(TRIM('\0' FROM user_email_token)!='' OR not user_email_token is null)",
			'user_email_token_expires' => null,

		];
		$isLinkLoginUser = $dbr->select(
			'user',
			['user_name','user_email_token'],
			$conds,
			__METHOD__,
			[
				'DISTINCT' => true,
				'ORDER BY' => 'user_name'
			]
		) ?: [];

		return $isLinkLoginUser->numRows() > 0;
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
			['user_name','user_email_token'],
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
			$groups = array_unique( (array)$GLOBALS['wgLinkLoginGroups'] );
		} else {
			$groups = array_intersect( array_unique( (array)$GLOBALS['wgLinkLoginGroups'] ), $groups );
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
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$res = $dbw->insert( 
			'll_attemptlog',
			[
				'll_attemptlog_ip' => $ip,
				'll_attemptlog_hash' => $hash,
				'll_attemptlog_timestamp' => time(),
			]);
	}
}