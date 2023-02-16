<?php

namespace MediaWiki\Extension\LinkLogin;

use ApiBase;
use \MediaWiki\MediaWikiServices;

class ApiLLmapping extends ApiBase {

    public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

    public function execute() {
		$params = $this->extractRequestParams();
        ApiBase::isWriteMode(true);
		$method = $params['method'];
        $user = $params['user'];
        $page = $params['page'];

		switch( $method ){
			case "map":
				$status = self::ll_map($user,$page);
				break;
			case "unmap":
				$status = self::ll_unmap($user,$page);
				break;
			case "setGroup":
				$status = self::ll_setGroup($user,$page);
				break;
			default:
				return 0;
		}
		
		$this->getResult()->addValue(
			null,
			"value",
			$status
		);
    }
	
    public function getAllowedParams() {
		return array(
			'method' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => false,
				//limit
			),
			'user' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => false,
				//limit
			),
            'page' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => false,
				//limit
			),
		);
	}

    public function ll_map($user, $page){
		//get user_id
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'user_name' => $user
        ];
		$user_id = $dbr->selectField(
			'user',
			'user_id',
			$conds,
            __METHOD__,
            [],
            []
		) ?: [];
		
		$page_id = $page;
		
		//insert a relation between user_id and page_id into table ll_mapping
		if( !empty( $user_id ) && !empty( $page_id ) ) {
			$dbw = $lb->getConnectionRef( DB_PRIMARY );
			$res = $dbw->insert( 
				'll_mapping',
				[
					'll_mapping_user' => $user_id,
					'll_mapping_page' => $page_id,
				]);
		} else {
			return 0;
		}

		return 1;
    }

    public function ll_unmap($user, $page){
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();

		//get user_id
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'user_name' => $user
        ];
		$user_id = $dbr->selectField(
			'user',
			'user_id',
			$conds,
            __METHOD__,
            [],
            []
		) ?: [];
		
		$page_id = $page;

		//delete entry from ll_mapping where user_id && page_id
		if( !empty( $user_id ) && !empty( $page_id ) ) {
			$dbw = $lb->getConnectionRef( DB_PRIMARY );
			$conds = [
				'll_mapping_user' => $user_id,
				'll_mapping_page' => $page_id
			];
			$res = $dbw->delete( 
				'll_mapping',
				$conds,
				__METHOD__,
			);
		} else {
			return 0;
		}
		return 1;
    }

	public function ll_setGroup($user, $page){
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();

		//get user_id
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'user_name' => $user
        ];
		$user_id = $dbr->selectField(
			'user',
			'user_id',
			$conds,
            __METHOD__,
            [],
            []
		) ?: [];

		$page_id = $page;

		//get all Categories connected to Page
		if( !empty( $user_id ) && !empty( $page_id ) ) {
			$dbr = $lb->getConnectionRef( DB_REPLICA );
			$conds = [
				'cl_from' => $page_id
			];
			$categories = $dbr->selectFieldValues(
				'categorylinks',
				'cl_to',
				$conds,
				__METHOD__,
				[],
				[]
			) ?: [];

			//get Groups connected to Categories
			if( !empty( $categories ) ) {
				$groups = [];
				foreach($categories as $category){
					$group_array = LinkLogin::getLinkLoginGroupsByCategory($category);
					foreach( $group_array as $group ){
						$groups[] = $group;
					}
				}
				//insert all Groups(relevant to Page) in relation with User into user_groups 
				if( !empty( $groups ) ) {
					foreach( $groups as $group ){
						$dbw = $lb->getConnectionRef( DB_PRIMARY );
						$res = $dbw->insert( 
							'user_groups',
							[
								'ug_user' => $user_id,
								'ug_group' => $group,
							]);
					}
				} else {
					return "empty groups";
				}
			} else {
				return "empty categories";
			} 
		} else {
			return "empty user_id or page_id";
		}
		return 1;
    }

}
?>

