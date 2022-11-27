<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;

class SpecialLinkLoginPages extends SpecialPage {
	function __construct() {
		parent::__construct( 'LinkLoginPages', 'loginlogs' );
    }

    //Par ist in Adressleiste übergebener Parameter z.B. Group
    function execute( $par ) {
        $this->setHeaders();

        //Show Users and Pages belonging to the group set in $par
        if( isset( $par ) && $par != '' ) {
            if( in_array($par, LinkLogin::getLinkLoginCategories() ) ) {
                $this->showCategoryDetails( $par );
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
        $categories = LinkLogin::getLinkLoginCategories();
        $output->addHTML('<table class="table table-bordered table-sm"><tr>');
        $output->addHTML('<th>' . 'Kategorien' . '</th>');
        $output->addHTML('</tr>');
        foreach( $categories as $category ) {
            $output->addHTML('<tr>');
            $url = SpecialPage::getTitleFor( 'LinkLoginPages' )->getLocalURL() . '/' . $category;
            $output->addHTML('<td>' . '<a href="' . $url . '">' . $category . '</a>' . '</td>');
            $output->addHTML('</tr>');
        }
		$output->addHTML('</table>');
    }

    /**
	 * Show Table of all Pages and Users belonging to a Category
	 * 
	 * @return void
	 */
	function showCategoryDetails($par) {
        $output = $this->getOutput();
        $output->addModules( 'ext.linklogin.mapping' );
        $groups = LinkLogin::getLinkLoginGroupsByCategory($par);


        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
            'cl_to' => $par
        ];
		$pages = $dbr->selectFieldValues(
			['categorylinks','page'],
			'page_title',
			$conds,
            __METHOD__,
            [],
            [
            'categorylinks' => [ 'INNER JOIN', [ 'cl_from=page_id'] ]
            ]
		) ?: [];
        
        $output->addHTML('<table class="table table-bordered table-sm"><tr>');
        $output->addHTML('<th>' . 'Page' . '</th>');
        $output->addHTML('<th>' . 'User' . '</th>');
        $output->addHTML('</tr>');

        //Hier Inhalte
        foreach( $pages as $page ) {
            $dbr = $lb->getConnectionRef( DB_REPLICA );
		    $conds = [
            'page_title' => $page
            ];
		    $user = $dbr->selectField(
			['ll_mapping','page','user'],
			'user_name',
			$conds,
            __METHOD__,
            [],
            [            
            'll_mapping' => [ 'INNER JOIN', ['ll_mapping_page=page_id'] ],
            'user' =>  ['INNER JOIN', ['ll_mapping_user=user_id'] ]
            ]
		    ) ?: [];

            $output->addHTML('<tr id=' . $page . '>');
            $output->addHTML('<td>' . $page . '</td>');

            if( !empty( $user ) ) {
                $output->addHTML('<td>');
                $output->addHTML('<span>' . $user . '</span>');
                $output->addHTML('<i class="fa fa-pen edit"></i>');
                $output->addHTML('<a class="unlink pages" style="float:right">' . '&times;' . '</a>');
                $output->addHTML('</td>');
            } else {             
                foreach( $groups as $group ) {
                    $dbr = $lb->getConnectionRef( DB_REPLICA );
                    $conds = [
                        'ug_group' => $group
                    ];
                    $users = $dbr->selectFieldValues(
                        ['user', 'user_groups'],
                        'user_name',
                        $conds,
                        __METHOD__,
                        [],
                        [
                        'user' => [ 'INNER JOIN', [ 'user_id=ug_user'] ]
                        ]
                    ) ?: [];
                }
            
                $output->addHTML('<td>');
                $output->addHTML('<div class="dropdown">');
                $output->addHTML('<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">');
                $output->addHTML('Neuem User zuordnen');
                $output->addHTML('</button>');
                $output->addHTML('<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">');
                foreach( $users as $user ){
                    $output->addHTML('<a class="dropdown-item user"' . $page . '">' . $user . '</a>');
                }
                $output->addHTML('</div>');

                //Neuen User anlegen
                $output->addHTML('<div class="md-form amber-textarea active-amber-textarea">');
                $output->addHTML('<label for="username_input">Einen neuen User anlegen</label>');
                $output->addHTML('<textarea id="username_input" class="md-textarea form-control" rows="1"></textarea>');
                $output->addHTML('<h5 id="usercheck" style="color: red;">**Username is missing</h5>');
                $output->addHTML('<button type="button" class="btn btn-primary create">Anlegen</button>');
                $output->addHTML('</div>');
                $output->addHTML('</tr>');
            }
        }
	    $output->addHTML('</table>');
    }





}