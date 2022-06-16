<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;
use Xml;
use HTMLForm;

class SpecialEditMailing extends SpecialPage {
	function __construct() {
		parent::__construct( 'EditMailing', 'mailings' );
	}

	function execute( $par ) {
		$this->checkPermissions('mailings');

		$request = $this->getRequest();
		$output = $this->getOutput();
		$output->addWikiTextAsInterface('{{#tweekihide:sidebar-right}}');
		$this->setHeaders();

		if( isset( $par ) ) {
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbr = $lb->getConnectionRef( DB_REPLICA );
			$conds = ['ll_mailing_id' => $par];
			$mailing = $dbr->selectRow(
				'll_mailing',
				['ll_mailing_id','ll_mailing_timestamp','ll_mailing_title','ll_mailing_group','ll_mailing_subject','ll_mailing_loginpage','ll_mailing_template'],
				$conds
			) ?: [];
		}

		$special = SpecialPage::getTitleFor( 'EditMailing' );

		$groups = array_flip( $GLOBALS['wgLinkLoginGroups'] );
		foreach( $groups as $key => &$group ) {
			$group = $key;
		}


	    $formDescriptor = [
	        'title' => [
	            'label-message' => 'linklogin-title',
	            'help-message' => 'linklogin-title-help',
	            'class' => 'HTMLTextField',
	        ],
	        'group' => [
	            'label-message' => 'linklogin-group',
	            'help-message' => 'linklogin-group-help',
	        	'options' => $groups,
	        	'class' => 'HTMLRadioField',
	        	'default' => 'test',
	        ],
	        'subject' => [
	            'label-message' => 'linklogin-subject',
	            'help-message' => 'linklogin-subject-help',
	            'class' => 'HTMLTextField',
	        ],
	        'template' => [
	            'label-message' => 'linklogin-template',
	            'help-message' => 'linklogin-template-help',
	            'class' => 'HTMLTextField',
	        ],
	        'loginpage' => [
	            'label-message' => 'linklogin-loginpage',
	            'help-message' => 'linklogin-loginpage-help',
	            'class' => 'HTMLTextField',
	        ],
	        'timestamp' => [
	        	'class' => 'HTMLHiddenField',
	        	'default' => time()
	        ],
	        'user' => [
	        	'class' => 'HTMLHiddenField',
	        	'default' => $this->getUser()->getId()
	        ]

	    ];
		
		if( $par ) {
			foreach( $formDescriptor as $key => $element ) {
				if( property_exists( $mailing, 'll_mailing_' . $key ) ) {
					$formDescriptor[$key]['default'] = $mailing->{'ll_mailing_' . $key};
				}
			}
			$formDescriptor['mailing'] = [
				'class' => 'HTMLHiddenField',
				'default' => $mailing->ll_mailing_id,
			];
		}

	    $htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
    	$htmlForm
    		->setSubmitCallback( [ $this, isset( $par ) ? 'edit' : 'create' ] )
    		->setSubmitTextMsg( isset( $par ) ? 'linklogin-edit' : 'linklogin-create' )
    		->show();
	}

	function create( $formData ) {
		$output = $this->getOutput();
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$res = $dbw->insert( 
			'll_mailing',
			[
				'll_mailing_title' => $formData['title'],
				'll_mailing_subject' => $formData['subject'],
				'll_mailing_template' => $formData['template'],
				'll_mailing_loginpage' => $formData['loginpage'],
				'll_mailing_user' => $formData['user'],
				'll_mailing_group' => $formData['group'],
				'll_mailing_timestamp' => $formData['timestamp'],
			]);

		$specialMailings = SpecialPage::getTitleFor( 'Mailings' );
		$output->redirect( $specialMailings->getLocalURL() );
	}

	function edit( $formData ) {
		$output = $this->getOutput();
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$conds = ['ll_mailing_id' => $formData['mailing']];
		$res = $dbw->update( 
			'll_mailing',
			[
				'll_mailing_title' => $formData['title'],
				'll_mailing_subject' => $formData['subject'],
				'll_mailing_template' => $formData['template'],
				'll_mailing_loginpage' => $formData['loginpage'],
				'll_mailing_user' => $formData['user'],
				'll_mailing_group' => $formData['group'],
				'll_mailing_timestamp' => $formData['timestamp'],
			], $conds);

		$specialMailings = SpecialPage::getTitleFor( 'Mailings' );
		$output->redirect( $specialMailings->getLocalURL() );
	}
}