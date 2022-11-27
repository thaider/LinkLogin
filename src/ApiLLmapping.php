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
			default:
				return 0;
		}
		
		$this->getResult()->addValue(
			null,
			"status",
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
		
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'page_title' => $page
        ];
		$page_id = $dbr->selectField(
			'page',
			'page_id',
			$conds,
            __METHOD__,
            [],
            []
		) ?: [];
		
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
		
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
			'page_title' => $page
        ];
		$page_id = $dbr->selectField(
			'page',
			'page_id',
			$conds,
            __METHOD__,
            [],
            []
		) ?: [];

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
}
?>

