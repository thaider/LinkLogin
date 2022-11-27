<?php

namespace MediaWiki\Extension\LinkLogin;

use \MediaWiki\MediaWikiServices;
use SpecialPage;

class SpecialLinkLoginUsers extends SpecialPage {
	function __construct() {
		parent::__construct( 'LinkLoginUsers', 'loginlogs' );
    }

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
        $output->addHTML('<th>' . 'Gruppen' . '</th>');
        $output->addHTML('</tr>');
        $output->addHTML('<tr>');
        foreach( $groups as $group ) {
            $url = SpecialPage::getTitleFor( 'LinkLoginUsers' )->getLocalURL() . '/' . $group;
            $output->addHTML('<td>' . '<a href="' . $url . '">' . $group . '</a>' . '</td>');
            $output->addHTML('</tr>');

        }
		$output->addHTML('</table>');
    }

    /**
	 * Show Table of all Users and Pages belonging to a group
	 * 
	 * @return void
	 */
	function showGroupDetails($par) {
        $output = $this->getOutput();
        $output->addModules( 'ext.linklogin.mapping' );

        //get users
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$conds = [
            'ug_group' => $par
        ];
		$users = $dbr->select(
			['user', 'user_groups'],
			['user_name','user_id'],
			$conds,
            __METHOD__,
            [],
            [
            'user' => [ 'INNER JOIN', [ 'user_id=ug_user'] ]
            ]
		) ?: [];

        $linked_pages = [];
        $used_pages = [];
        foreach( $users as $user ) {
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		    $dbr = $lb->getConnectionRef( DB_REPLICA );
		    $conds = [
            'll_mapping_user' => $user->user_id,
            ];
		    $pages = $dbr->selectFieldValues(
			['ll_mapping','user', 'page'],
			'page_title',
			$conds,
            __METHOD__,
            [],
            [
            'user' => [ 'INNER JOIN', [ 'user_id=ll_mapping_user'] ],
            'page' => [ 'INNER JOIN', [ 'page_id=ll_mapping_page'] ],
            ]
		) ?: [];
            if( !empty($pages) ) {
                $linked_pages[$user->user_name] = $pages;
                foreach($pages as $page){
                    $used_pages[] = $page;
                }
            }   
        }


        //get pages belonging to a category
        //Nach mehreren Kategorien gleichzeitig suchen?
        $unlinked_pages = [];
        $categories = LinkLogin::getLinkLoginCategoriesByGroup($par);
        foreach( $categories as $category){
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );
		    $conds = [
                'cl_to' => $category
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
            if( !empty($pages) ) {
                foreach($pages as $page){
                $unlinked_pages[] = $page;
                }
            }   
        }

        $unlinked_pages = array_unique($unlinked_pages);
        foreach( $unlinked_pages as $key => $unlinked_page ) {
            if( in_array($unlinked_page,$used_pages) ) {
                unset($unlinked_pages[$key]);
            }
        }

        $output->addHTML('<table class="table table-bordered table-sm"><tr>');
        $output->addHTML('<th>' . 'User' . '</th>');
        $output->addHTML('<th>' . 'Pages' . '</th>');
        $output->addHTML('</tr>');
        //Hier Inhalte
        foreach( $users as $user ) {
            $output->addHTML('<tr id=' . '"' . $user->user_name . '"' . '>');
            $output->addHTML('<td>' . $user->user_name . '</td>');
            $output->addHTML('<td>');
            if( array_key_exists($user->user_name, $linked_pages)){
                $output->addHTML('<ul>');
                foreach( $linked_pages[$user->user_name] as $linked_page){
                    $output->addHTML('<li>');
                    $output->addHTML('<span>' . $linked_page . '</span>');
                    $output->addHTML('<a class="unlink users" style="float:right">' . '&times;' . '</a>');
                    $output->addHTML('</li>');
                }
                $output->addHTML('</ul>');
            }
            $output->addHTML('<div class="dropdown">');
            $output->addHTML('<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">');
            $output->addHTML('Neue Seite zuordnen');
            $output->addHTML('</button>');
            $output->addHTML('<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">');
            foreach($unlinked_pages as $unlinked_page){
                //nur Pages zeigen, die dem User noch nicht zugeordnet ist
                if(!in_array($unlinked_page,$linked_pages)){
                    $output->addHTML('<a class="dropdown-item pages"' . $user->user_name . '">' . $unlinked_page . '</a>');
                }
            }
           
            $output->addHTML('</div></div>');
            $output->addHTML('</td>');
            $output->addHTML('</tr>');
        }
		$output->addHTML('</table>');

    }





}