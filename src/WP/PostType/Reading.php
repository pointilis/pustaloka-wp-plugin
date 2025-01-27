<?php

namespace Pointilis\Pustaloka\WP\PostType;

/**
 * This class responsible to register the Reading post type.
 * including register post meta, taxonomies, and BuddyPress activity tracking.
 */
class Reading {

    protected string $post_type = 'reading';
    protected array $meta_fields = array( 
        'from_datetime', 
        'to_datetime', 
        'from_page',
        'to_page',
        'screenshots',
        'pause_log',
        'pause_duration',
        'challenge', // related to `challenge` post type

        // this field not part of post meta
        // for custom data purpose
        'book',
        'number_of_pages',
        'progress',
    );

    public function __construct() {
        // Don't forget to add the 'buddypress-activity' support!
        add_post_type_support( $this->post_type, 'buddypress-activity' );

        add_action( 'init', array( $this, 'register' ) );
        add_action( 'rest_api_init', array( $this, 'register_post_meta' ), 10, 1 );
        add_filter( 'rest_pre_insert_reading', array( $this, 'rest_pre_insert_reading' ), 10, 2 );
        add_filter( 'rwmb_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'rwmb_reading_detail_before_save_post', array( $this, 'rmbw_reading_detail_before_save_post' ), 10, 1 );
    }

    /**
     * Register the Reading post type.
     */
    public function register() {
        $labels = array(
            'name'                  => _x( 'Readings', 'Post Type General Name', 'pustaloka' ),
            'singular_name'         => _x( 'Reading', 'Post Type Singular Name', 'pustaloka' ),
            'menu_name'             => __( 'Reading', 'pustaloka' ),
            'name_admin_bar'        => __( 'Reading', 'pustaloka' ),
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

            'bp_activity_admin_filter' => __( 'Now reading', 'pustaloka' ),
            'bp_activity_front_filter' => __( 'Reading', 'pustaloka' ),
            'bp_activity_new_post'     => __( '%1$s reading a <a href="%2$s">book</a>', 'pustaloka' ),
            'bp_activity_new_post_ms'  => __( '%1$s reading a <a href="%2$s">book</a>, on the site %3$s', 'pustaloka' ),
        );

        $args = array(
            'label'                 => __( 'Reading', 'pustaloka' ),
            'description'           => __( 'Readings mapped by community', 'pustaloka' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'author' ),
            'taxonomies'            => array( 'post_tag' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-admin-page',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'readings',
            'rest_controller_class' => 'WP_REST_Posts_Controller_Extend',
        );

        // Show post type on the BuddyPress activity stream
        if ( bp_is_active( 'activity' ) ) {
            $args['bp_activity'] = array(
                'component_id'             => buddypress()->activity->id,
                'action_id'                => 'post_reading',
                'bp_activity_admin_filter' => __( 'Now reading', 'pustaloka' ),
                'bp_activity_front_filter' => __( 'Reading', 'pustaloka' ),
                'contexts'                 => array( 'activity', 'member' ),
                'activity_comment'         => true,
                'bp_activity_new_post'     => __( '%1$s reading a <a href="%2$s">book</a>', 'pustaloka' ),
                'bp_activity_new_post_ms'  => __( '%1$s reading a <a href="%2$s">book</a>, on the site %3$s', 'pustaloka' ),
                'position'                 => 100,
            );
        }

        register_post_type( $this->post_type, $args );
    }

    /**
     * Register post meta for the Reading post type.
     */
    public function register_post_meta() {
        foreach ( $this->meta_fields as $field ) {
            $sanitize_callback = 'sanitize_text_field';
            $type = 'string';
            $show_in_rest = true;
            $single = true;

            if ( in_array( $field, array( 'screenshots' ) ) ) {
                $type = 'array';
                $single = false;
                $show_in_rest = array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'integer',
                        ),
                    ),
                    'prepare_callback' => function( $value, $post_id, $field ) {
                        $attachment = get_post( $value );
                        return array(
                            'ID' => $attachment->ID,
                            'src' => $attachment->guid,
                            'caption' => $attachment->post_excerpt,
                        );
                    }
                );
            }

            if ( $field === 'challenge' ) {
                $type = 'integer';
                $sanitize_callback = null;
                $single = true;
                $show_in_rest = array(
                    'type' => 'object',
                    'prepare_callback' => function( $value ) {
                        $to_page    = 0;
                        $challenge  = get_post( $value );
                        $readings   = get_posts( array( 
                            'posts_per_page' => 1,
                            'post_type' => 'reading',
                            'orderby' => 'ID',
                            'order' => 'DESC',
                            'meta_query' => array(
                                array(
                                    'key' => 'challenge',
                                    'value' => $value,
                                )
                            ),
                        ) );

                        if ( $readings ) {
                            $reading        = $readings[0];
                            $reading_id     = $reading->ID;
                            $to_page        = (int) rwmb_get_value( 'to_page', array(), $reading_id );
                        }

                        // set last read page
                        $challenge->latest_reading_page = $to_page;
                        return $challenge;
                    }
                );
            }

            // calculate progress as percentage
            // compare `number_of_pages` from `challenge` post type
            if ( $field === 'progress' ) {
                $show_in_rest = array(
                    'type' => 'integer',
                    'prepare_callback' => function() {
                        $post_id            = get_the_ID();
                        $challenge_id       = (int) rwmb_get_value( 'challenge', array(), $post_id );
                        $number_of_pages    = (int) rwmb_get_value( 'number_of_pages', array(), $challenge_id );
                        $to_page            = (int) rwmb_get_value( 'to_page', array(), $post_id );

                        if ( ! empty( $to_page ) && ! empty( $number_of_pages ) ) {
                            $percentage = ( $to_page / $number_of_pages ) * 100;
                        } else {
                            $percentage = 0;
                        }

                        return round( $percentage, 1);
                    }
                );
            }

            if ( $field === 'number_of_pages' ) {
                $show_in_rest = array(
                    'type' => 'number',
                    'prepare_callback' => function() {
                        $challenge_id = rwmb_get_value( 'challenge', array(), get_the_ID() );
                        return (int) rwmb_get_value( 'number_of_pages', array(), $challenge_id );
                    }
                );
            }

            if ( $field === 'book' ) {
                $type               = 'string';
                $sanitize_callback  = null;
                $show_in_rest = array(
                    'type' => 'object',
                    'prepare_callback' => function() {
                        $challenge_id   = rwmb_get_value( 'challenge', array(), get_the_ID() );
                        $book_id        = rwmb_get_value( 'book', array(), $challenge_id );
                        $book_author    = wp_get_object_terms( $book_id, array( 'book_author' ) );

                        $featured_media_url = get_the_post_thumbnail_url( $book_id, 'large' );
                        $default_image      = PUSTALOKA_URL . 'public/images/placeholder_book.png';
                        $book_cover         = $featured_media_url ? $featured_media_url : $default_image;

                        return array(
                            'book_author'   => $book_author,
                            'book_cover'    => $book_cover,
                        );
                    }
                );
            }

            // pause log
            if ( $field == 'pause_log' ) {
                $type               = 'array';
                $single             = true;
                $sanitize_callback  = null;
                $show_in_rest       = array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'array',
                        ),
                    ),
                );
            }

            // pause duration
            if ( $field == 'pause_duration' ) {
                $type = 'integer';
            }

            register_meta( 'post', $field,  array(
                'type'              => $type,
                'description'       => 'The ' . $field . ' of the reading',
                'object_subtype'    => $this->post_type,
                'sanitize_callback' => $sanitize_callback,
                'required'          => true,
                'single'            => $single,
                'show_in_rest'      => $show_in_rest,
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                }
            ) );
        }
    }

    /**
     * Validate the Reading post type before insert.
     */
    public function rest_pre_insert_reading( $prepared_post, $request ) {
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
     * Register meta boxes for the Reading post type.
     */
    public function register_meta_boxes( $meta_boxes ) {
        $meta_boxes[] = array(
            'id'         => 'reading_detail',
            'title'      => __( 'Reading Detail', 'pustaloka' ),
            'post_types' => array( $this->post_type ),
            'context'    => 'side',
            'priority'   => 'default',
            'fields'     => array(
                array(
                    'id'   => 'challenge',
                    'name' => __( 'Challenge', 'pustaloka' ),
                    'type' => 'post',
                    'parent' => false, // use post_parent as 'challenge'
                    'post_type' => 'challenge',
                    'field_type' => 'select_advanced',
                    'placeholder' => __( 'Select a challenge', 'pustaloka' ),
                    'required' => true,
                    'query_args' => array(
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'post_author' => -1,
                    ),
                ),
                array(
                    'id'   => 'from_datetime',
                    'name' => __( 'Start Datetime', 'pustaloka' ),
                    'type' => 'datetime',
                ),
                array(
                    'id'   => 'to_datetime',
                    'name' => __( 'End Datetime', 'pustaloka' ),
                    'type' => 'datetime',
                ),
                array(
                    'id'   => 'from_page',
                    'name' => __( 'Start Page', 'pustaloka' ),
                    'type' => 'number',
                ),
                array(
                    'id'   => 'to_page',
                    'name' => __( 'End Page', 'pustaloka' ),
                    'type' => 'number',
                ),
                array(
                    'id'               => 'screenshots',
                    'name'             => __( 'Screenshots', 'pustaloka' ),
                    'type'             => 'file_upload',
                    'force_delete'     => false,
                    'mime_type'        => 'image/*',
                    'max_status'       => false,
                ),
                array(
                    'id'               => 'pause_log',
                    'name'             => __( 'Pause Log', 'pustaloka' ),
                    'type'             => 'text_list',
                    'clone'            => true,
                    'options'          => array(
                        'id'            => __( 'ID', 'pustaloka' ),
                        'from_datetime' => __( 'From Datetime', 'pustaloka' ),
                        'to_datetime'   => __( 'To Datetime', 'pustaloka' ),
                    ),
                ),
                array(
                    'id'   => 'pause_duration',
                    'name' => __( 'Pause Duration', 'pustaloka' ),
                    'type' => 'number',
                ),
            ),
        );

        return $meta_boxes;
    }

    /**
     * Update metabox field before save.
     */
    public function rmbw_reading_detail_before_save_post( $post_id ) {
        // set `challenge` value to `post_parent`
        if ( empty( $_POST['challenge'] ) ) {
            $_POST['challenge'] = $_POST['parent_id'];
        }

        rwmb_request()->set_post_data( $_POST );
    }

}