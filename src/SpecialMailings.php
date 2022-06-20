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
		if( isset( $par ) && $par != '' ) {
			$this->showDetails( $par );
			return true;
		}

		// Mailings Overview
		$this->showOverview();
	}


	/**
	 * Show overview of all mailings
	 */
	function showOverview() {
		$output = $this->getOutput();

		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');
		$output->addWikiTextAsInterface('<div class="mb-4">[[Special:EditMailing|<span class="btn btn-primary">' . wfMessage('linklogin-create')->text() . '</span>]]</div>');

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [];
		$mailings = $dbr->select(
			'll_mailing',
			['ll_mailing_id','ll_mailing_timestamp','ll_mailing_title','ll_mailing_subject','ll_mailing_template','ll_mailing_group','ll_mailing_loginpage', 'll_mailing_user'],
			$conds
		) ?: [];

		$output->addHTML('<table class="table table-bordered table-sm"><tr>');
		foreach( [ 'created', 'title', 'subject', 'template', 'loginpage', 'group' ] as $header ) {
			$output->addHTML('<th>' . wfMessage('linklogin-' . $header) . '</th>');
		}
		$output->addHTML('<th class="semorg-showedit" style="width:100px"></th></tr>');

		$special = SpecialPage::getTitleFor( 'Mailings' );
		$specialEdit = SpecialPage::getTitleFor( 'EditMailing' );
		foreach( $mailings as $row ) {
			$creator = User::newFromId( $row->ll_mailing_user );
			$output->addHTML('<tr>');
			$output->addHTML('<td>' . date( wfMessage('linklogin-dateformat')->text(), $row->ll_mailing_timestamp ) . '<div style="font-size:small">by ' . $creator->getName() . '</div></td>');
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

		//$output->addWikiTextAsInterface('Tabelle der archivierten Mailings');
	}


	/**
	 * Show Details for one specific Mailing with the option to send it
	 * 
	 * @param Integer $par Mailing ID
	 */
	function showDetails( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$special = SpecialPage::getTitleFor( 'Mailings' );

		$uom = MediaWikiServices::getInstance()->getUserOptionsManager();

		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');
		$output->addHTML('<div class="text-center mb-4"><a href="' . $special->getLocalURL() . '" class="btn btn-primary">' . wfMessage('linklogin-overview')->text() . '</a></div>');

		$sentCount = 0;
		$unsentCount = 0;
		$newsentCount = 0;
		$sendableCount = 0;
		$notincludedCount = 0;
		$exceptedCount = 0;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = ['ll_mailing_id' => $par];
		$mailing = $dbr->selectRow(
			'll_mailing',
			['ll_mailing_id','ll_mailing_timestamp','ll_mailing_title','ll_mailing_group','ll_mailing_subject','ll_mailing_template','ll_mailing_loginpage','ll_mailing_signature','ll_mailing_replyto','ll_mailing_only','ll_mailing_except'],
			$conds
		) ?: [];

		$recipients = LinkLogin::getLinkLoginUsers();

		if ( $request->getText( 'll-send', false ) ) {
			$newsentCount = $this->send( $mailing, $recipients );
		}
		if( $request->getText( 'll-mark-sent', false ) ) {
			$this->markAsSent( $mailing, $recipients );
		}
		if( $request->getText( 'll-mark-unsent', false ) ) {
			$this->markAsUnsent( $mailing, $recipients );
		}

		$output->addWikiTextAsInterface( '\'\'\'' . $mailing->ll_mailing_title . '\'\'\' versenden');

		$only = $this->createOnlyExcept( $mailing->ll_mailing_only );
		$except = $this->createOnlyExcept( $mailing->ll_mailing_except );

		$conds = ['ll_mailinglog_mailing' => $par];
		$sent_res = $dbr->select(
				'll_mailinglog',
				['ll_mailinglog_user','ll_mailinglog_timestamp'],
				$conds
			);

		$sent = [];
		foreach( $sent_res as $row ) {
			$sent[$row->ll_mailinglog_user] = $row->ll_mailinglog_timestamp;
		}

		$recipients_sent = [];
		$recipients_unsent = [];
		foreach( $recipients as $recipient ) {
			$user = User::newFromName( $recipient->user_name );
			if( $mailing->ll_mailing_only != '' && !in_array( $recipient->user_name, $only ) ) {
				$notincludedCount++;
				continue;
			}
			if( $mailing->ll_mailing_except != '' && in_array( $recipient->user_name, $except ) ) {
				$exceptedCount++;
				continue;
			}
			$recipient->user = $user;
			foreach( array_keys( $GLOBALS['wgLinkLoginPreferences'] ) as $preference ) {
				$recipient->{$preference} = $uom->getOption( $recipient->user, $preference );
			}
			if( in_array($user->getId(), array_keys( $sent )) ) {
				$recipient->ll_mailinglog_timestamp = $sent[$user->getId()];
				$recipients_sent[] = $recipient;
				$sentCount++;
			} else {
				$recipients_unsent[] = $recipient;
				$unsentCount++;
				if( $recipient->email ) {
					$sendableCount++;
				}
			}
		}

		if( $only && count( $only ) > 0 ) {
			$output->addWikiTextAsInterface( '<div class="float-right">{{#semorg-collapse:ll-only}}</div>' . wfMessage('linklogin-only-count', count($only))->text() . ( $notincludedCount > 0 ? ( ' (' . wfMessage( 'linklogin-only-excluded', $notincludedCount ) . ')' ) : '' ) . ':' );
			$output->addHTML( '<div class="collapse border p-3 m-3" id="ll-only">' . join( ',', $only ) . '</div>' );
		}

		if( $except && count( $except ) > 0 ) {
			$output->addWikiTextAsInterface( '<div class="float-right">{{#semorg-collapse:ll-except}}</div>' . wfMessage('linklogin-except-count', count($except))->text() . ( $exceptedCount > 0 ? ( ' (' . wfMessage( 'linklogin-except-excluded', $exceptedCount ) . ')' ) : '' ) . ':' );
			$output->addHTML( '<div class="collapse border p-3 m-3" id="ll-except">' . join( ',', $except ) . '</div>' );
		}

		$output->addWikiMsg( 'linklogin-sent', $newsentCount );
		$output->addWikiMsg( 'linklogin-sent-total', $sentCount );
		if( $unsentCount > 0 ) {
			$columns = wfMessage('linklogin-columns')->exists() ? explode(',',wfMessage('linklogin-columns')->text()) : array_keys( $GLOBALS['wgLinkLoginPreferences'] );

			$output->addWikiTextAsInterface( '<h3 class="mt-4 mb-3">' . wfMessage( 'linklogin-unsent', $unsentCount )->text() . ' (' . wfMessage( 'linklogin-sendable', $sendableCount )->text() . '): </h3>');

			// Start form
			$output->addHTML( Xml::element( 'form', [
				'action' => $special->getLocalURL() . '/' . $par,
				'method' => 'POST'
			], null ) );

			// Table of users without mailing
			$output->addHTML('<table class="table table-bordered table-sm"><tr>');
			foreach( [ 'recipient', 'username' ] as $header ) {
				$output->addHTML('<th' . ( $header == 'recipient' ? ' class="text-center"' : '' ) . '>' . wfMessage('linklogin-' . $header) . '</th>');
			}
			foreach( $columns as $column ) {
				$output->addHTML('<th>&lt;' . ucfirst( $column ) . '&gt;</th>');
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
				foreach( $columns as $column ) {
					if( property_exists( $recipient, $column ) ) {
						$output->addHTML( '<td>' . $recipient->{$column} . '</td>' );
					} else {
						$params = '';
						foreach( array_keys( $GLOBALS['wgLinkLoginPreferences'] ) as $preference ) {
							$params .= '|' . $preference . '=' . $recipient->{$preference};
						}
						$output->addHTML('<td>');
						$output->addWikiTextAsInterface( '<div>{{ll-' . $column . $params . '}}</div>' );
						$output->addHTML('</td>');
					}
				}
				$output->addHTML( '</tr>' );
			}
			$output->addHTML('</table>');


			// Example
			$example_user = reset($recipients_unsent );
			$output->addWikiTextAsInterface( '<div class="float-right">{{#semorg-collapse:ll-example}}</div>' . wfMessage('linklogin-example', $example_user->user_name, $mailing->ll_mailing_template)->text() );
			$output->addHTML('<div class="border p-3 m-3 collapse" id="ll-example">');
			$bodyWikiText = $this->createBody( $example_user, $mailing );
			$output->addWikiTextAsInterface( $bodyWikiText );
			$output->addHTML( '<br>---<br>' . $mailing->ll_mailing_signature );
			$output->addHTML('</div>');


			// Render submit button to send mailing
			$output->addHTML( Xml::element( 'input', [ 
					'type' => 'submit', 
					'name' => 'll-send', 
					'value' => wfMessage( 'linklogin-send' )->text() 
				] )
			 . '&#160;' );
			$output->addHTML( Xml::element( 'input', [ 
					'type' => 'submit', 
					'name' => 'll-mark-sent', 
					'value' => wfMessage( 'linklogin-mark-sent' )->text() 
				] )
			 . '&#160;' );
			$output->addHTML( "</form>" );

		}

		if( $sentCount > 0 ) {
			$output->addWikiTextAsInterface( '<h3 class="mt-4 mb-3"><div class="float-right" style="font-size:smaller">{{#semorg-collapse:ll-sent-list}}</div>' . wfMessage( 'linklogin-sent-heading', $sentCount )->text() . ': </h3>');

			// Start form
			$output->addHTML('<div class="collapse" id="ll-sent-list">');
			$output->addHTML( Xml::element( 'form', [
				'action' => $special->getLocalURL() . '/' . $par,
				'method' => 'POST'
			], null ) );

			// Table of users with mailing
			$output->addHTML('<table class="table table-bordered table-sm"><tr>');
			foreach( [ 'mark-unsent', 'username', 'date-sent' ] as $header ) {
				$output->addHTML('<th ' . ( $header == 'mark-unsent' ? 'class="text-center"' : '' ) . '>' . wfMessage('linklogin-' . $header) . '</th>');
			}
			$output->addHTML('</tr>');

			usort( $recipients_sent, function($a,$b) {
				return $b->ll_mailinglog_timestamp <=> $a->ll_mailinglog_timestamp;
			});

			foreach( $recipients_sent as $recipient ) {
				$output->addHTML( '<tr>' );
				$output->addHTML( '<td class="text-center">' );
				if( $recipient->email ) {
					$output->addHTML( Xml::element( 'input', [ 
						'type' => 'checkbox', 
						'name' => 'll-recipient[]',
						'value' => $recipient->user->getId(),
						//'checked' => true
					] ) );
				}
				$output->addHTML( '</td>' );
				$output->addHTML( '<td>' );
				$output->addWikiTextAsInterface( '<div>[[Special:EditUser/' . $recipient->user_name . '|' . $recipient->user_name . ']]</div>' );
				$output->addHTML( '</td>' );
				$output->addHTML('<td>' . date( wfMessage('linklogin-dateformat')->text(), $recipient->ll_mailinglog_timestamp ) . '</td>');
				$output->addHTML( '</tr>' );
			}
			$output->addHTML('</table>');

			// Render submit button to send mailing
			$output->addHTML( Xml::element( 'input', [ 
					'type' => 'submit', 
					'name' => 'll-mark-unsent', 
					'value' => wfMessage( 'linklogin-mark-unsent' )->text() 
				] )
			 . '&#160;' );
			$output->addHTML( "</form></div>" );
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
					$subject = $mailing->ll_mailing_subject;
					$options = [];
					if( $mailing->ll_mailing_replyto ) {
						$options['replyTo'] = new MailAddress( $mailing->ll_mailing_replyto );
					}

					$bodyWikiText = $this->createBody( $recipient, $mailing );
					$bodyHtml = $parser->parse( $bodyWikiText, $title, $opt, true, true )->getText();
					if( $mailing->ll_mailing_signature ) {
						$bodyHtml .= '<br>---<br>' . $mailing->ll_mailing_signature;
					}
					$bodyText = strip_tags( $bodyHtml );
					
					$emailer = MediaWikiServices::getInstance()->getEmailer();
					$status = $emailer->send(
						$to,
						$from,
						$subject,
						$bodyText,
						$bodyHtml,
						$options
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
	 * Mark mailing as sent for specific users
	 * 
	 * @return Integer Number of marked users
	 */
	function markAsSent( $mailing, $recipients ) {
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

		foreach( $recipients as $recipient ) {
			if( in_array( $recipient->user_name, $selected_recipients ) ) {
				$user = User::newFromName( $recipient->user_name );
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

		return $counter;
	}


	/**
	 * Mark mailing as unsent for specific users
	 * 
	 * @return Integer Number of unmarked users
	 */
	function markAsUnsent( $mailing, $recipients ) {
		$request = $this->getRequest();

		$selected_recipients = $request->getArray('ll-recipient');

		if( is_null( $selected_recipients ) ) {
			return 0;
		}

		$counter = 0;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$conds = [
			'll_mailinglog_mailing' => $mailing->ll_mailing_id,
			'll_mailinglog_user' => $selected_recipients
		];
		$res = $dbw->delete(
			'll_mailinglog',
			$conds
		);
		return $dbw->affectedRows();
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


	/**
	 * Create list of users that should be considered
	 * 
	 * Entries on different lines will be combined with a logical AND (users have to be in/excepted from all of the lists)
	 * 
	 * @param $users
	 * 
	 * @return Array List of users
	 */
	function createOnlyExcept( $users ) {
		$parser = \MediaWiki\MediaWikiServices::getInstance()->getParser();
		$title = SpecialPage::getTitleFor( 'Mailings' );
		$opt   = new ParserOptions;
		$list = false;
		foreach( explode( "\n", $users ) as $line ) {
			if( $line == '' ) {
				continue;
			}
			$line = $parser->parse( $line, $title, $opt, false, true )->getText();
			$line = strip_tags( $line );
			$line = htmlspecialchars_decode($line);
			$line = explode( ',', $line );
			foreach( $line as &$user ) {
				$user = trim( $user );
			}
			unset($user);
			$line = array_unique( $line );
			$list = $list === false ? $line : array_intersect( $list, $line );
		}
		return $list;
	}
}