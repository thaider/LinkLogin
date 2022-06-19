<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;
use User;

class SpecialLoginLog extends SpecialPage {
	function __construct() {
		parent::__construct( 'LoginLog', 'loginlogs' );
	}

	function execute( $par ) {
		$this->checkPermissions('loginlogs');
		$this->setHeaders();

		$output = $this->getOutput();

		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [];
		$options = [];
		$options['LIMIT'] = 100;
		$options['ORDER BY'] = 'll_loginlog_timestamp DESC';
		$loginlogs = $dbr->select(
			'll_loginlog',
			['ll_loginlog_hash','ll_loginlog_user','ll_loginlog_timestamp'],
			$conds,
			__METHOD__,
			$options
		) ?: [];

		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		foreach( [ 'user', 'hash', 'timestamp' ] as $header ) {
			$output->addHTML('<th>' . wfMessage('linklogin-loginlog-' . $header) . '</th>');
		}
		$output->addHTML('</tr>');

		foreach( $loginlogs as $row ) {
			$user = User::newFromId( $row->ll_loginlog_user );
			$output->addHTML('<tr>');
			$output->addHTML('<td>' . $user->getName() . '</td>');
			$output->addHTML('<td>' . $row->ll_loginlog_hash . '</td>');
			$output->addHTML('<td>' . date( wfMessage('linklogin-datetimeformat')->text(), $row->ll_loginlog_timestamp ) . '</td>');
		}
		$output->addHTML('</table>');
	}
}