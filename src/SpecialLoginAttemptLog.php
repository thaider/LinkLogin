<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;

class SpecialLoginAttemptLog extends SpecialPage {
	function __construct() {
		parent::__construct( 'LoginAttemptLog', 'loginlogs' );
	}

	function execute( $par ) {
		$this->checkPermissions('loginlogs');
		$this->setHeaders();

		$output = $this->getOutput();

		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$attemptlogs = $dbr->newSelectQueryBuilder()
			->select( ['ll_attemptlog_hash','ll_attemptlog_ip','ll_attemptlog_timestamp'] )
			->from( 'll_attemptlog' )
			->orderBy( 'll_attemptlog_timestamp DESC' )
			->limit( 100 )
			->caller( __METHOD__ )
			->fetchResultSet();

		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		foreach( [ 'ip', 'hash', 'timestamp' ] as $header ) {
			$output->addHTML('<th>' . wfMessage('linklogin-attemptlog-' . $header) . '</th>');
		}
		$output->addHTML('</tr>');

		foreach( $attemptlogs as $row ) {
			$output->addHTML('<tr>');
			$output->addHTML('<td>' . $row->ll_attemptlog_ip . '</td>');
			$output->addHTML('<td>' . $row->ll_attemptlog_hash . '</td>');
			$output->addHTML('<td>' . date( wfMessage('linklogin-datetimeformat')->text(), $row->ll_attemptlog_timestamp ) . '</td>');
		}
		$output->addHTML('</table>');
	}
}