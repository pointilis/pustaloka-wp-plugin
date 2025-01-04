<?php 

namespace Pointilis\Pustaloka\WP\PostType;

class Taxonomy {

    public function __construct() {
        add_action( 'init', array( $this, 'register_taxonomy' ), 0 );
    }

    // Register Custom Taxonomy
    function register_taxonomy() {
        $capabilities = array(
            'manage_terms'               => 'edit_posts',
            'edit_terms'                 => 'edit_posts',
            'delete_terms'               => 'delete_posts',
            'assign_terms'               => 'publish_posts',
        );

        // Book Authors
        $labels = array(
            'name'                       => _x( 'Authors', 'Taxonomy General Name', 'pustaloka' ),
            'singular_name'              => _x( 'Author', 'Taxonomy Singular Name', 'pustaloka' ),
            'menu_name'                  => __( 'Authors', 'pustaloka' ),
            'all_items'                  => __( 'All Authors', 'pustaloka' ),
            'parent_item'                => __( 'Parent Author', 'pustaloka' ),
            'parent_item_colon'          => __( 'Parent Author:', 'pustaloka' ),
            'new_item_name'              => __( 'New Author Name', 'pustaloka' ),
            'add_new_item'               => __( 'Add New Author', 'pustaloka' ),
            'edit_item'                  => __( 'Edit Author', 'pustaloka' ),
            'update_item'                => __( 'Update Author', 'pustaloka' ),
            'view_item'                  => __( 'View Author', 'pustaloka' ),
            'separate_items_with_commas' => __( 'Separate authors with commas', 'pustaloka' ),
            'add_or_remove_items'        => __( 'Add or remove authors', 'pustaloka' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'pustaloka' ),
            'popular_items'              => __( 'Popular Authors', 'pustaloka' ),
            'search_items'               => __( 'Search Authors', 'pustaloka' ),
            'not_found'                  => __( 'Not Found', 'pustaloka' ),
            'no_terms'                   => __( 'No authors', 'pustaloka' ),
            'items_list'                 => __( 'Authors list', 'pustaloka' ),
            'items_list_navigation'      => __( 'Authors list navigation', 'pustaloka' ),
        );

        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
            'capabilities'               => $capabilities,
        );

        register_taxonomy( 'book_author', array( 'book' ), $args );

        // Book Publishers
        $labels = array(
            'name'                       => _x( 'Publishers', 'Taxonomy General Name', 'pustaloka' ),
            'singular_name'              => _x( 'Publisher', 'Taxonomy Singular Name', 'pustaloka' ),
            'menu_name'                  => __( 'Publishers', 'pustaloka' ),
            'all_items'                  => __( 'All Publishers', 'pustaloka' ),
            'parent_item'                => __( 'Parent Publisher', 'pustaloka' ),
            'parent_item_colon'          => __( 'Parent Publisher:', 'pustaloka' ),
            'new_item_name'              => __( 'New Publisher Name', 'pustaloka' ),
            'add_new_item'               => __( 'Add New Publisher', 'pustaloka' ),
            'edit_item'                  => __( 'Edit Publisher', 'pustaloka' ),
            'update_item'                => __( 'Update Publisher', 'pustaloka' ),
            'view_item'                  => __( 'View Publisher', 'pustaloka' ),
            'separate_items_with_commas' => __( 'Separate publishers with commas', 'pustaloka' ),
            'add_or_remove_items'        => __( 'Add or remove publishers', 'pustaloka' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'pustaloka' ),
            'popular_items'              => __( 'Popular Publishers', 'pustaloka' ),
            'search_items'               => __( 'Search Publishers', 'pustaloka' ),
            'not_found'                  => __( 'Not Found', 'pustaloka' ),
            'no_terms'                   => __( 'No publishers', 'pustaloka' ),
            'items_list'                 => __( 'Publishers list', 'pustaloka' ),
            'items_list_navigation'      => __( 'Publishers list navigation', 'pustaloka' ),
        );

        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
            'capabilities'               => $capabilities,
        );

        register_taxonomy( 'book_publisher', array( 'book' ), $args );
    }

}