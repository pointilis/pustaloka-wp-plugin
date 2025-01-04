<?php

namespace Pointilis\Pustaloka\WP\PostType;

/**
 * This class responsible to register the Challenge post type.
 * including register post meta, taxonomies, and BuddyPress activity tracking.
 */
class Challenge {

    protected string $post_type = 'challenge';
    protected array $meta_fields = array( 
        'book',
        'book_author', // read-only, placeholder for book author from book post type
        'number_of_pages', // coming from book, user need re-check again
        'from_datetime', // starting point reading date
        'to_datetime',
        'status', // maybe 'on going', 'cancelled', etc

        'reading', // get reading progress
    );

    public function __construct() {
        // Don't forget to add the 'buddypress-activity' support!
        add_post_type_support( $this->post_type, 'buddypress-activity' );

        add_action( 'init', array( $this, 'register' ) );
        add_action( 'rest_api_init', array( $this, 'register_post_meta' ) );
        add_filter( 'rest_pre_insert_challenge', array( $this, 'rest_pre_insert_challenge' ), 10, 2 );
        add_filter( 'rwmb_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'rwmb_challenge_detail_before_save_post', array( $this, 'rmbw_challenge_detail_before_save_post' ), 10, 1 );
        
        add_filter( 'wp_post_revision_meta_keys', array( $this, 'revision_meta_keys' ), 10, 2 );
    }

    /**
     * Register the Challenge post type.
     */
    public function register() {
        $labels = array(
            'name'                  => _x( 'Challenges', 'Post Type General Name', 'pustaloka' ),
            'singular_name'         => _x( 'Challenge', 'Post Type Singular Name', 'pustaloka' ),
            'menu_name'             => __( 'Challenge', 'pustaloka' ),
            'name_admin_bar'        => __( 'Challenge', 'pustaloka' ),
            'archives'              => __( 'Item Archives', 'pustaloka' ),
            'attributes'            => __( 'Item Attributes', 'pustaloka' ),
            'parent_item_colon'     => __( 'Parent Item:', 'pustaloka' ),
            'all_items'             => __( 'All Items', 'pustaloka' ),
            'add_new_item'          => __( 'Add New Item', 'pustaloka' ),
            'add_new'               => __( 'Add New', 'pustaloka' ),
            'new_item'              => __( 'New Item', 'pustaloka' ),
            'edit_item'             => __( 'Edit Item', 'pustaloka' ),
            'update_item'           => __( 'Update Item', 'pustaloka' ),
            'view_item'             => __( 'View Item', 'pustaloka' ),
            'view_items'            => __( 'View Items', 'pustaloka' ),
            'search_items'          => __( 'Search Item', 'pustaloka' ),
            'not_found'             => __( 'Not found', 'pustaloka' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'pustaloka' ),
            'featured_media'        => __( 'Featured Image', 'pustaloka' ),
            'set_featured_media'    => __( 'Set featured image', 'pustaloka' ),
            'remove_featured_media' => __( 'Remove featured image', 'pustaloka' ),
            'use_featured_media'    => __( 'Use as featured image', 'pustaloka' ),
            'insert_into_item'      => __( 'Insert into item', 'pustaloka' ),
            'uploaded_to_this_item' => __( 'Uploaded to this item', 'pustaloka' ),
            'items_list'            => __( 'Items list', 'pustaloka' ),
            'items_list_navigation' => __( 'Items list navigation', 'pustaloka' ),
            'filter_items_list'     => __( 'Filter items list', 'pustaloka' ),

            'bp_activity_admin_filter' => __( 'New challenge introduced', 'pustaloka' ),
            'bp_activity_front_filter' => __( 'Challenge', 'pustaloka' ),
            'bp_activity_new_post'     => __( '%1$s introduce a new <a href="%2$s">challenge</a>', 'pustaloka' ),
            'bp_activity_new_post_ms'  => __( '%1$s introduce a new <a href="%2$s">challenge</a>, on the site %3$s', 'pustaloka' ),
        );

        $args = array(
            'label'                 => __( 'Challenge', 'pustaloka' ),
            'description'           => __( 'Challenges mapped by community', 'pustaloka' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'author' ),
            'taxonomies'            => array( 'post_tag' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-flag',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'challenges',
            'rest_controller_class' => 'WP_REST_Posts_Controller_Extend',
            'autosave_rest_controller_class' => 'WP_REST_Autosaves_Controller_Extend',
            'revisions_rest_controller_class' => '',
        );

        // Show post type on the BuddyPress activity stream
        if ( bp_is_active( 'activity' ) ) {
            $args['bp_activity'] = array(
                'component_id'             => buddypress()->activity->id,
                'action_id'                => 'post_challenge',
                'bp_activity_admin_filter' => __( 'Introduce a new challenge', 'pustaloka' ),
                'bp_activity_front_filter' => __( 'Challenge', 'pustaloka' ),
                'contexts'                 => array( 'activity', 'member' ),
                'activity_comment'         => true,
                'bp_activity_new_post'     => __( '%1$s introduce a new <a href="%2$s">challenge</a>', 'custom-textdomain' ),
                'bp_activity_new_post_ms'  => __( '%1$s introduce a new <a href="%2$s">challenge</a>, on the site %3$s', 'custom-textdomain' ),
                'position'                 => 100,
            );
        }

        register_post_type( $this->post_type, $args );
    }

    /**
     * Register post meta for the Challenge post type.
     */
    public function register_post_meta( $wp_rest_server ) {   
        foreach ( $this->meta_fields as $field ) {
            $type               = 'string';
            $show_in_rest       = true;
            $sanitize_callback  = 'sanitize_text_field';
            $single             = true;

            if ( in_array( $field, array( 'number_of_pages' ) ) ) {
                $type = 'number';
            }

            if ( $field === 'book' ) {
                $type = 'integer';
                $sanitize_callback = null;
                $show_in_rest = array(
                    'type' => 'object',
                    'prepare_callback' => function( $value ) {
                        $post               = get_post( $value );
                        $featured_media_url = get_the_post_thumbnail_url( $value, 'large' );
                        $default_image      = PUSTALOKA_URL . 'public/images/placeholder_book.png';
                        $post->featured_media_url = $featured_media_url ? $featured_media_url : $default_image;
                        
                        return $post;
                    }
                );
            }

            if ( $field === 'book_author' ) {
                $type               = 'string';
                $sanitize_callback  = null;
                $show_in_rest = array(
                    'type' => 'array',
                    'prepare_callback' => function() {
                        $book_id        = rwmb_get_value( 'book', array(), get_the_ID() );
                        $book_authors   = wp_get_object_terms( $book_id, array( 'book_author' ) );

                        return $book_authors;
                    }
                );
            }

            // calculate progress as percentage
            // compare `number_of_pages` from `challenge` post type
            if ( $field === 'reading' ) {
                $show_in_rest = array(
                    'type' => 'integer',
                    'prepare_callback' => function() {
                        $to_page            = null;
                        $last_reading_date  = null;
                        $post_id            = get_the_ID(); // challenge id
                        $book_id            = (int) rwmb_get_value( 'book', array(), $post_id );
                        $number_of_pages    = (int) rwmb_get_value( 'number_of_pages', array(), $book_id );
                        $readings           = get_posts( array( 
                            'posts_per_page' => 1,
                            'post_type' => 'reading',
                            'orderby' => 'ID',
                            'order' => 'DESC',
                            'meta_query' => array(
                                array(
                                    'key' => 'challenge',
                                    'value' => $post_id,
                                )
                            ),
                        ) );
                        
                        if ( $readings ) {
                            $reading        = $readings[0];
                            $reading_id     = $reading->ID;
                            $last_reading_date = $reading->post_date;
                            $from_page      = (int) rwmb_get_value( 'from_page', array(), $reading_id );
                            $to_page        = (int) rwmb_get_value( 'to_page', array(), $reading_id );
                        }

                        if ( ! empty( $to_page ) && ! empty( $number_of_pages ) ) {
                            $percentage = ( $to_page / $number_of_pages ) * 100;
                        } else {
                            $percentage = 0;
                        }

                        return array(
                            'progress' => round( $percentage, 1),
                            'last_reading_datetime' => $last_reading_date,
                            'from_page' => $from_page,
                            'to_page' => $to_page,
                        );
                    }
                );
            }

            register_meta( 'post', $field,  array(
                'type'              => $type,
                'description'       => 'The ' . $field . ' of the challenge',
                'object_subtype'    => $this->post_type,
                'sanitize_callback' => $sanitize_callback,
                'single'            => $single,
                'show_in_rest'      => $show_in_rest,
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                }
            ) );
        }
    }

    /**
     * Validate the Challenge post type before insert.
     */
    public function rest_pre_insert_challenge( $prepared_post, $request ) {
        if ( empty( $prepared_post->ID ) && empty( $prepared_post->post_title ) ) {
            return new \WP_Error(
                'rest_invalid_field',
                __( 'Missing title field', 'pustaloka' ),
                array( 'status' => 400 )
            );
        }

        return $prepared_post;
    }

    /**
     * Register meta boxes for the Challenge post type.
     */
    public function register_meta_boxes( $meta_boxes ) {
        $meta_boxes[] = array(
            'id'         => 'challenge_detail',
            'title'      => __( 'Challenge Detail', 'pustaloka' ),
            'post_types' => array( $this->post_type ),
            'context'    => 'side',
            'priority'   => 'default',
            'fields'     => array(
                array(
                    'id'        => 'book',
                    'name'      => __( 'Book', 'pustaloka' ),
                    'type'      => 'post',
                    'parent'    => false, // use post_parent as 'book'
                    'post_type' => 'book',
                    'field_type' => 'select_advanced',
                    'placeholder' => __( 'Select a book', 'pustaloka' ),
                    'required'  => true,
                    'query_args' => array(
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'post_author' => -1,
                        'post_type' => 'book',
                    ),
                ),
                array(
                    'id'   => 'number_of_pages',
                    'name' => __( 'Total pages from the book', 'pustaloka' ),
                    'type' => 'number',
                    'required' => true,
                ),
                array(
                    'id'   => 'from_datetime',
                    'name' => __( 'Start reading from?', 'pustaloka' ),
                    'type' => 'datetime',
                ),
                array(
                    'id'   => 'to_datetime',
                    'name' => __( 'Reading finish at?', 'pustaloka' ),
                    'type' => 'datetime',
                ),
                array(
                    'id'   => 'status',
                    'name' => __( 'Status', 'pustaloka' ),
                    'type' => 'select',
                    'required'  => true,
                    'options'   => array(
                        ''          => __( 'Select status', 'pustaloka' ),
                        'ongoing'   => __( 'On Going', 'pustaloka' ),
                        'done'      => __( 'Done', 'pustaloka' ),
                        'pending'   => __( 'Pending', 'pustaloka' ),
                        'cancelled' => __( 'Cancelled', 'pustaloka' ),
                    ),
                ),
            ),
        );

        return $meta_boxes;
    }

    /**
     * Update metabox field before save.
     */
    public function rmbw_challenge_detail_before_save_post( $post_id ) {
        // set `book` value to `post_parent`
        if ( empty( $_POST['book'] ) ) {
            $_POST['book'] = $_POST['parent_id'];
        }

        rwmb_request()->set_post_data( $_POST );
    }

    /**
     * Include meta keys to be revisioned.
     */
    public function revision_meta_keys( $keys, $post_type ) {
        if ( $post_type === $this->post_type ) {
            $keys = array_merge( $keys, $this->meta_fields );
        }
        
        return $keys;
    }

}