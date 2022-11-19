<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;

class SpecialLinkLoginUsers extends SpecialPage {
	function __construct() {
		parent::__construct( 'LinkLoginUsers', 'loginlogs' );
    }

    //Par ist in Adressleiste übergebener Parameter z.B. Group
    function execute( $par ) {
        $this->setHeaders();

        //Show Users and Pages belonging to the group set in $par
        if( isset( $par ) && $par != '' ) {
            if( in_array($par, LinkLogin::getLinkLoginGroups(true) ) ) {
                $this->showGroupDetails( $par );
                return true;
            }
			
		}

        $this->showOverview();
    }

    /**
	 * Show overview of all groups
	 * 
	 * @return void
	 */
	function showOverview() {
        $output = $this->getOutput();
        $groups = LinkLogin::getLinkLoginGroups(true);
        $output->addHTML('<table class="table table-bordered table-sm"><tr>');
        $output->addHTML('<th>' . wfMessage('Gruppen') . '</th>');
        $output->addHTML('</tr>');
        $output->addHTML('<tr>');
        foreach( $groups as $group ) {
            $output->addHTML('<td>' . $group . '</td>');
        }
        $output->addHTML('</tr>');
		$output->addHTML('</table>');
    }

    /**
	 * Show Table of all Users and Pages belonging to a group
	 * 
	 * @return void
	 */
	function showGroupDetails() {
        
        return $test;
    }





}