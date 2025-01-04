<?php

namespace Pointilis\Pustaloka\WP\PostType;

/**
 * This class responsible to register the Library post type.
 * including register post meta, taxonomies, and BuddyPress activity tracking.
 */
class Library {

    protected string $post_type = 'library';
    protected array $meta_fields = array( 
        'street', 
        'city', 
        'state', 
        'postal_code', 
        'country', 
        'latitude', 
        'longitude', 
        'access',
        'gallery',
    );

    public function __construct() {
        // Don't forget to add the 'buddypress-activity' support!
        add_post_type_support( $this->post_type, 'buddypress-activity' );

        add_action( 'init', array( $this, 'register' ) );
        add_action( 'rest_api_init', array( $this, 'register_post_meta' ) );
        add_filter( 'rest_pre_insert_library', array( $this, 'rest_pre_insert_library' ), 10, 2 );
        add_filter( 'rwmb_meta_boxes', array( $this, 'register_meta_boxes' ) );
    }

    /**
     * Register the Library post type.
     */
    public function register() {
        $labels = array(
            'name'                  => _x( 'Libraries', 'Post Type General Name', 'pustaloka' ),
            'singular_name'         => _x( 'Library', 'Post Type Singular Name', 'pustaloka' ),
            'menu_name'             => __( 'Library', 'pustaloka' ),
            'name_admin_bar'        => __( 'Library', 'pustaloka' ),
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

            'bp_activity_admin_filter' => __( 'New library introduced', 'pustaloka' ),
            'bp_activity_front_filter' => __( 'Library', 'pustaloka' ),
            'bp_activity_new_post'     => __( '%1$s introduce a new <a href="%2$s">library</a>', 'pustaloka' ),
            'bp_activity_new_post_ms'  => __( '%1$s introduce a new <a href="%2$s">library</a>, on the site %3$s', 'pustaloka' ),
        );

        $args = array(
            'label'                 => __( 'Library', 'pustaloka' ),
            'description'           => __( 'Libraries mapped by community', 'pustaloka' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'author' ),
            'taxonomies'            => array( 'category', 'post_tag' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-building',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'libraries',
            'rest_controller_class' => 'WP_REST_Posts_Controller_Extend',
        );

        // Show post type on the BuddyPress activity stream
        if ( bp_is_active( 'activity' ) ) {
            $args['bp_activity'] = array(
                'component_id'             => buddypress()->activity->id,
                'action_id'                => 'post_library',
                'bp_activity_admin_filter' => __( 'Introduce a new library', 'pustaloka' ),
                'bp_activity_front_filter' => __( 'Library', 'pustaloka' ),
                'contexts'                 => array( 'activity', 'member' ),
                'activity_comment'         => true,
                'bp_activity_new_post'     => __( '%1$s introduce a new <a href="%2$s">library</a>', 'custom-textdomain' ),
                'bp_activity_new_post_ms'  => __( '%1$s introduce a new <a href="%2$s">library</a>, on the site %3$s', 'custom-textdomain' ),
                'position'                 => 100,
            );
        }

        register_post_type( $this->post_type, $args );
    }

    /**
     * Register post meta for the Library post type.
     */
    public function register_post_meta() {
        foreach ( $this->meta_fields as $field ) {
            $type = 'string';
            $show_in_rest = true;
            $single = true;

            if ( in_array( $field, array( 'latitude', 'longitude' ) ) ) {
                $type = 'number';
            }

            if ( in_array( $field, array( 'gallery' ) ) ) {
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

            register_meta( 'post', $field,  array(
                'type'              => $type,
                'description'       => 'The ' . $field . ' of the library',
                'object_subtype'    => $this->post_type,
                'sanitize_callback' => 'sanitize_text_field',
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
     * Validate the Library post type before insert.
     */
    public function rest_pre_insert_library( $prepared_post, $request ) {
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
     * Register meta boxes for the Library post type.
     */
    public function register_meta_boxes( $meta_boxes ) {
        $meta_boxes[] = array(
            'id'         => 'library_detail',
            'title'      => __( 'Library Detail', 'pustaloka' ),
            'post_types' => array( $this->post_type ),
            'context'    => 'side',
            'priority'   => 'default',
            'fields'     => array(
                array(
                    'id'   => 'street',
                    'name' => __( 'Street', 'pustaloka' ),
                    'type' => 'text',
                ),
                array(
                    'id'   => 'city',
                    'name' => __( 'City', 'pustaloka' ),
                    'type' => 'text',
                ),
                array(
                    'id'   => 'state',
                    'name' => __( 'State', 'pustaloka' ),
                    'type' => 'text',
                ),
                array(
                    'id'   => 'postal_code',
                    'name' => __( 'Postal Code', 'pustaloka' ),
                    'type' => 'text',
                ),
                array(
                    'id'   => 'country',
                    'name' => __( 'Country', 'pustaloka' ),
                    'type' => 'text',
                ),
                array(
                    'id'   => 'latitude',
                    'name' => __( 'Latitude', 'pustaloka' ),
                    'type' => 'float',
                ),
                array(
                    'id'   => 'longitude',
                    'name' => __( 'Longitude', 'pustaloka' ),
                    'type' => 'float',
                ),
                array(
                    'id'   => 'access',
                    'name' => __( 'Access', 'pustaloka' ),
                    'type' => 'select',
                    'options' => array(
                        'public'  => __( 'Public', 'pustaloka' ),
                        'private' => __( 'Private', 'pustaloka' ),
                    ),
                ),
                array(
                    'id'               => 'gallery',
                    'name'             => __( 'Gallery', 'pustaloka' ),
                    'type'             => 'file_upload',
                    'force_delete'     => false,
                    'mime_type'        => 'image/*',
                    'max_status'       => false,
                )
            ),
        );

        return $meta_boxes;
    }

}