<?php

/**
 * Plugin Name: Pustaloka
 * Description: Pustaloka is a plugin for book lovers to share their mind.
 * Version: 1.0
 * Author: Pointilis Noktah Teknologi (@rahman)
 * License: GPLv2 or later
 */

use Pointilis\Pustaloka\BP\BP_Filters;
use Pointilis\Pustaloka\BP\XMembers\BP_Component as BP_XMembers_Component;
use Pointilis\Pustaloka\WP\PostType\Library;
use Pointilis\Pustaloka\WP\PostType\Book;
use Pointilis\Pustaloka\WP\PostType\Challenge;
use Pointilis\Pustaloka\WP\PostType\Reading;
use Pointilis\Pustaloka\WP\PostType\Taxonomy;
use Pointilis\Pustaloka\WP\PostType\Attachment;
use Pointilis\Pustaloka\WP\PostType\Review;
use Pointilis\Pustaloka\WP\User\User;
use Pointilis\Pustaloka\WP\User\Register_Meta;
use Pointilis\Pustaloka\Core\Filters;
use Pointilis\Pustaloka\Core\WP_REST_Posts_Controller_Extend;
use Pointilis\Pustaloka\Core\WP_REST_Users_Controller_Extend;
use Pointilis\Pustaloka\Core\WP_REST_Autosaves_Controller_Extend;
use Pointilis\Pustaloka\Core\WP_REST_Revisions_Controller_Extend;

define( 'PUSTALOKA_VERSION', '1.0' );
define( 'PUSTALOKA_PATH', realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR );
define( 'PUSTALOKA_URL', plugin_dir_url( __FILE__ ) );

require __DIR__ . '/vendor/autoload.php';

add_action( 'init', function() {
    $user = new User();
    $user->extend_user_capabilities();
} );

add_action( 'plugins_loaded', function() {
    new BP_Filters();
    new Filters();
    new Taxonomy();
    new Challenge();
    new Book();
    new Reading();
    new Review();
    // new Library();
    // new Attachment();
    
    $register_meta = new Register_Meta();
    $register_meta->register();
} );

add_action( 'bp_setup_components', function() {
    if ( bp_is_active( 'members' ) ) {
	    buddypress()->xmembers = new BP_XMembers_Component();
    }
}, 6 );

add_action( 'rest_api_init', function() {
    // post controller
    $args = array(
        'public'   => true,
        '_builtin' => false,
    );
 
    $output = 'names'; // names or objects, note names is the default
    $operator = 'and'; // 'and' or 'or'
    $post_types = get_post_types( $args, $output, $operator ); 

    foreach ( $post_types as $post_type ) {
        $contoller = new WP_REST_Posts_Controller_Extend( $post_type );
        $contoller->register_routes();

        // autosaves controller
        $contoller = new WP_REST_Autosaves_Controller_Extend( $post_type );
        $contoller->register_routes();

        // revisions controller 
        $contoller = new WP_REST_Revisions_Controller_Extend( $post_type );
        $contoller->register_routes();
    }

    // user controller
    $contoller = new WP_REST_Users_Controller_Extend();
    $contoller->register_routes();
}, 98 );
