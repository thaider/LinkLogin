<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;
use Xml;
use User;
use MailAddress;
use Title;
use ParserOptions;

class SpecialMailings extends SpecialPage {
	function __construct() {
		parent::__construct( 'Mailings', 'mailings' );
	}

	function execute( $par ) {
		$this->checkPermissions('mailings');
		$this->setHeaders();
		
		// Mailing Details
		if( isset( $par ) ) {
			$this->showDetails( $par );
			return true;
		}

		// Mailings Overview
		$this->showOverview();
	}


	function showOverview() {
		$output = $this->getOutput();

		$output->addWikiTextAsInterface('[[Special:EditMailing|' . wfMessage('linklogin-create')->text() . ']]');

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [];
		$mailings = $dbr->select(
			'll_mailing',
			['ll_mailing_id','ll_mailing_timestamp','ll_mailing_title','ll_mailing_subject','ll_mailing_template','ll_mailing_group','ll_mailing_loginpage'],
			$conds
		) ?: [];

		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		foreach( [ 'timestamp', 'title', 'subject', 'template', 'loginpage', 'group' ] as $header ) {
			$output->addHTML('<th>' . wfMessage('linklogin-' . $header) . '</th>');
		}
		$output->addHTML('<th class="semorg-showedit" style="width:100px"></th></tr>');

		$special = SpecialPage::getTitleFor( 'Mailings' );
		$specialEdit = SpecialPage::getTitleFor( 'EditMailing' );
		foreach( $mailings as $row ) {
			$output->addHTML('<tr>');
			$output->addHTML('<td>' . date( wfMessage('linklogin-dateformat')->text(), $row->ll_mailing_timestamp ) . '</td>');
			$output->addHTML('<td>' . $row->ll_mailing_title . '</td>');
			$output->addHTML('<td>' . $row->ll_mailing_subject . '</td>');
			$output->addHTML('<td>');
			$output->addWikiTextAsInterface ( '[[Template:' . $row->ll_mailing_template . '|' . $row->ll_mailing_template . ']]' );
			$output->addHTML('</td>');
			$output->addHTML('<td>');
			$output->addWikiTextAsInterface ( '[[' . $row->ll_mailing_loginpage . ']]' );
			$output->addHTML('</td>');
			$output->addHTML('<td>' . $row->ll_mailing_group . '</td>');
			$output->addHTML('<td class="semorg-showedit">');
			foreach( [ 
				'edit' => [
					'icon' => 'pen',
					'url' => $specialEdit->getLocalURL() . '/' . $row->ll_mailing_id,
				],
				'send' => [
					'icon' => 'paper-plane',
					'url' => $special->getLocalURL() . '/' . $row->ll_mailing_id,
				],
				'archive' => [
					'icon' => 'archive',
					'url' => '',
				],
				'delete' => [
					'icon' => 'trash',
					'url' => '',
				]
			] as $action => $options ) {
				if( $options['url'] != '' ) {
					$output->addHTML('<a href="' . $options['url'] . '" title="' . wfMessage('linklogin-' . $action )->text() . '" data-toggle="tooltip"><i class="fa fa-' . $options['icon'] . '"></i></a> ');
				}
			}
			$output->addHTML('</td>');
		}
		$output->addHTML('</table>');

		$output->addWikiTextAsInterface('Tabelle der archivierten Mailings');
	}


	function showDetails( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$special = SpecialPage::getTitleFor( 'Mailings' );

		$uom = MediaWikiServices::getInstance()->getUserOptionsManager();

		$output->addHTML('<div class="text-center"><a href="' . $special->getLocalURL() . '">' . wfMessage('linklogin-overview')->text() . '</a></div>');

		$sentCount = 0;
		$unsentCount = 0;
		$newsentCount = 0;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = ['ll_mailing_id' => $par];
		$mailing = $dbr->selectRow(
			'll_mailing',
			['ll_mailing_id','ll_mailing_timestamp','ll_mailing_title','ll_mailing_group','ll_mailing_subject','ll_mailing_template','ll_mailing_loginpage'],
			$conds
		) ?: [];

		$recipients = LinkLogin::getLinkLoginUsers();

		if ( $request->getText( 'll-send', false ) ) {
			$newsentCount = $this->send( $mailing, $recipients );
		}

		$output->addWikiTextAsInterface( '\'\'\'' . $mailing->ll_mailing_title . '\'\'\' versenden');

		$conds = ['ll_mailinglog_mailing' => $par];
		$sent = $dbr->selectFieldValues(
				'll_mailinglog',
				'll_mailinglog_user',
				$conds
			) ?: [];

		$recipients_sent = [];
		$recipients_unsent = [];
		foreach( $recipients as $recipient ) {
			$user = User::newFromName( $recipient->user_name );
			$recipient->user = $user;
			if( in_array($user->getId(), $sent) ) {
				$recipients_sent[] = $recipient;
				$sentCount++;
			} else {
				foreach( array_keys( $GLOBALS['wgLinkLoginPreferences'] ) as $preference ) {
					$recipient->{$preference} = $uom->getOption( $recipient->user, $preference );
				}
				$recipients_unsent[] = $recipient;
				$unsentCount++;
			}
		}

		$output->addWikiTextAsInterface( $newsentCount . ' neu verschickt');
		$output->addWikiTextAsInterface( $sentCount . ' ingesamt verschickt');
		if( $unsentCount > 0 ) {
			$output->addWikiTextAsInterface( $unsentCount . ' (weitere) Empfänger*innen verfügbar:');

			// Start form
			$output->addHTML( Xml::element( 'form', [
				'class'  => 'EmailPage',
				'action' => $special->getLocalURL() . '/' . $par,
				'method' => 'POST'
			], null ) );


			// Table of users without mailing
			$output->addHTML('<table class="table table-bordered table-sm"><tr>');
			foreach( [ 'recipient', 'username' ] as $header ) {
				$output->addHTML('<th>' . wfMessage('linklogin-' . $header) . '</th>');
			}
			foreach( array_keys( $GLOBALS['wgLinkLoginPreferences'] ) as $header ) {
				$output->addHTML('<th>&lt;' . $header . '&gt;</th>');
			}
			$output->addHTML('</tr>');

			foreach( $recipients_unsent as $recipient ) {
				$output->addHTML( '<tr>' );
				$output->addHTML( '<td class="text-center">' );
				if( $recipient->email ) {
					$output->addHTML( Xml::element( 'input', [ 
						'type' => 'checkbox', 
						'name' => 'll-recipient[]',
						'value' => $recipient->user_name,
						//'checked' => true
					] ) );
				}
				$output->addHTML( '</td>' );
				$output->addHTML( '<td>' );
				$output->addWikiTextAsInterface( '<div>[[Special:EditUser/' . $recipient->user_name . '|' . $recipient->user_name . ']]</div>' );
				$output->addHTML( '</td>' );
				foreach( array_keys( $GLOBALS['wgLinkLoginPreferences'] ) as $preference ) {
					$output->addHTML( '<td>' . $recipient->{$preference} . '</td>' );
				}
				$output->addHTML( '</tr>' );
			}
			$output->addHTML('</table>');


			// Example
			$example_user = reset($recipients_unsent );
			$output->addWikiTextAsInterface( 'Example output for ' . $example_user->user_name . ' using template [[Template:' . $mailing->ll_mailing_template . '|' . $mailing->ll_mailing_template . ']]:');
			$output->addHTML('<div class="border p-3 m-3">');
			$bodyWikiText = $this->createBody( $example_user, $mailing );
			$output->addWikiTextAsInterface( $bodyWikiText );
			$output->addHTML('</div>');


			// Render submit button to send mailing
			$output->addHTML( Xml::element( 'input', [ 
					'type' => 'submit', 
					'name' => 'll-send', 
					'value' => wfMessage( 'linklogin-send' )->text() 
				] )
			 . '&#160;' );
			$output->addHTML( "</form>" );
		}
	}


	/**
	 * Send Mailing
	 * 
	 * @return Integer Number of sent Mailings
	 */
	function send( $mailing, $recipients ) {
		$request = $this->getRequest();

		$selected_recipients = $request->getArray('ll-recipient');

		if( is_null( $selected_recipients ) ) {
			return 0;
		}

		$counter = 0;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = ['ll_mailinglog_mailing' => $mailing->ll_mailing_id];
		$sent = $dbr->selectFieldValues(
			'll_mailinglog',
			'll_mailinglog_user',
			$conds
		) ?: [];

		$uom = MediaWikiServices::getInstance()->getUserOptionsManager();

		$parser = \MediaWiki\MediaWikiServices::getInstance()->getParser();
		$title = Title::newFromText( $mailing->ll_mailing_loginpage );
		$opt   = new ParserOptions;

		foreach( $recipients as $recipient ) {
			if( in_array( $recipient->user_name, $selected_recipients ) ) {
				$user = User::newFromName( $recipient->user_name );
				$recipient->user = $user;
				$email = $uom->getOption( $user, 'email' );
				if( !in_array($user->getId(), $sent) && !is_null( $email ) ) {
					$to = [ new MailAddress( $email ) ];
					$from = new MailAddress( $GLOBALS['wgPasswordSender'], wfMessage('Emailsender')->text() );
					$subject = $mailing->ll_mailing_title;

					$bodyWikiText = $this->createBody( $recipient, $mailing );
					$bodyHtml = $parser->parse( $bodyWikiText, $title, $opt, true, true )->getText();
					$bodyText = strip_tags( $bodyHtml );
					
					$emailer = MediaWikiServices::getInstance()->getEmailer();
					$status = $emailer->send(
						$to,
						$from,
						$subject,
						$bodyText,
						$bodyHtml
					);

					if( !$status->ok ) {
						$errors = [];
						foreach( $status->errors as $error ) {
							$errors[] = wfMessage( $error['message'], $error['params'] )->text();
						}
						die(var_dump( $errors ) );
					} else {
						$dbw = $lb->getConnectionRef( DB_PRIMARY );
						$res = $dbw->insert( 
							'll_mailinglog',
							[
								'll_mailinglog_mailing' => $mailing->ll_mailing_id,
								'll_mailinglog_user' => $user->getId(),
								'll_mailinglog_timestamp' => time(),
							]);

						$counter++;
					}
				}
			}
		}

		return $counter;
	}


	/**
	 * Create message body
	 * 
	 * @param $user
	 * @param $mailing
	 * 
	 * @return String Message body as wikitext
	 */
	function createBody( $recipient, $mailing ) {
		$uom = MediaWikiServices::getInstance()->getUserOptionsManager();

		$title = Title::newFromText($mailing->ll_mailing_loginpage);

		$params = '';
		foreach( $GLOBALS['wgLinkLoginPreferences'] as $key => $preference ) {
			$params .= '|' . $key . '=' . $uom->getOption( $recipient->user, $key );
		}

		$body = '{{' . $mailing->ll_mailing_template . '
			|username=' . $recipient->user_name . '
			|login=' . $title->getFullURL([ 'login' => $recipient->user_email_token ]) . $params . '
		}}';

		return $body;
	}
}