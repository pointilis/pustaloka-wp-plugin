<?php

namespace Pointilis\Pustaloka\WP\PostType;

/**
 * This class responsible to register the Book post type.
 * including register post meta, taxonomies, and BuddyPress activity tracking.
 */
class Book {

    protected string $post_type = 'book';
    protected array $meta_fields = array( 
        'series_title', // same as `excerpt`
        'blurb', // same as `content`
        'edition',
        'isbn',
        'issn',
        'publish_year',
        'number_of_pages',
        'collocation',
        'call_number',
        'languages',
        'source',
        'publish_places',
        'classification', // same sa `post_tag`
        'notes',
        'availability',
        'format',
        'library',
        'blurb_media',
    );

    public function __construct() {
        // Don't forget to add the 'buddypress-activity' support!
        add_post_type_support( $this->post_type, 'buddypress-activity' );

        add_action( 'init', array( $this, 'register' ) );
        add_action( 'rest_api_init', array( $this, 'register_post_meta' ) );
        add_filter( 'rest_pre_insert_book', array( $this, 'rest_pre_insert_book' ), 10, 2 );
        add_filter( 'rwmb_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'rwmb_book_detail_before_save_post', array( $this, 'rmbw_book_detail_before_save_post' ), 10, 1 );
        
        add_filter( 'wp_post_revision_meta_keys', array( $this, 'revision_meta_keys' ), 10, 2 );
    }

    /**
     * Register the Book post type.
     */
    public function register() {
        $labels = array(
            'name'                  => _x( 'Books', 'Post Type General Name', 'pustaloka' ),
            'singular_name'         => _x( 'Book', 'Post Type Singular Name', 'pustaloka' ),
            'menu_name'             => __( 'Book', 'pustaloka' ),
            'name_admin_bar'        => __( 'Book', 'pustaloka' ),
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

            'bp_activity_admin_filter' => __( 'New book introduced', 'pustaloka' ),
            'bp_activity_front_filter' => __( 'Book', 'pustaloka' ),
            'bp_activity_new_post'     => __( '%1$s introduce a new <a href="%2$s">book</a>', 'pustaloka' ),
            'bp_activity_new_post_ms'  => __( '%1$s introduce a new <a href="%2$s">book</a>, on the site %3$s', 'pustaloka' ),
        );

        $args = array(
            'label'                 => __( 'Book', 'pustaloka' ),
            'description'           => __( 'Books mapped by community', 'pustaloka' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'author' ),
            'taxonomies'            => array( 'category', 'post_tag', 'book_author', 'book_publisher' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-book',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'books',
            'rest_controller_class' => 'WP_REST_Posts_Controller_Extend',
            'autosave_rest_controller_class' => 'WP_REST_Autosaves_Controller_Extend',
            'revisions_rest_controller_class' => '',
        );

        // Show post type on the BuddyPress activity stream
        if ( bp_is_active( 'activity' ) ) {
            $args['bp_activity'] = array(
                'component_id'             => buddypress()->activity->id,
                'action_id'                => 'post_book',
                'bp_activity_admin_filter' => __( 'Introduce a new book', 'pustaloka' ),
                'bp_activity_front_filter' => __( 'Book', 'pustaloka' ),
                'contexts'                 => array( 'activity', 'member' ),
                'activity_comment'         => true,
                'bp_activity_new_post'     => __( '%1$s introduce a new <a href="%2$s">book</a>', 'custom-textdomain' ),
                'bp_activity_new_post_ms'  => __( '%1$s introduce a new <a href="%2$s">book</a>, on the site %3$s', 'custom-textdomain' ),
                'position'                 => 100,
            );
        }

        register_post_type( $this->post_type, $args );
    }

    /**
     * Register post meta for the Book post type.
     */
    public function register_post_meta() {
        $sanitize_callback = null;

        foreach ( $this->meta_fields as $field ) {
            $type = 'string';
            $show_in_rest = array(
                'schema' => array(
                    'type' => 'integer',
                    'context' => array( 'view', 'edit' ),
                ),
            );

            if ( in_array( $field, array( 'languages', 'publish_places' ) ) ) {
                $type = 'array';
                $show_in_rest = array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'string',
                            'context' => array( 'view', 'edit' ),
                        ),
                    ),
                );
            }

            if ( in_array( $field, array( 'number_of_pages', 'publish_year' ) ) ) {
                $type = 'integer';
            }

            // only for `library` field
            // added more information about instance of library
            if ( $field === 'library' ) {
                $show_in_rest = array(
                    'schema' => array(
                        'type' => 'integer',
                        'context' => array( 'view', 'edit' ),
                    ),
                    'prepare_callback' => function( $value, $request ) {
                        $library = get_post_meta( get_the_ID(), 'library', true );

                        if ( $library ) {
                            $library = get_post( $library );
                            return array(
                                'id' => $library->ID,
                                'name' => $library->post_title,
                                'url' => get_permalink( $library->ID ),
                            );
                        }

                        return null; 
                    },
                );
            }

            // blurb media
            if ( $field === 'blurb_media' ) {
                $type = 'integer';
                $show_in_rest = array(
                    'schema' => array(
                        'type' => $type,
                        'context' => array( 'view', 'edit' ),
                    ),
                    'prepare_callback' => function( $value, $request ) {
                        $media = get_post_meta( get_the_ID(), 'blurb_media', true );

                        if ( $media ) {
                            $media = wp_get_attachment_image_src( $media, 'large' );
                            return array(
                                'id' => $media[1],
                                'url' => $media[0],
                            );
                        }

                        return null;
                    },
                );
            }

            register_meta( 'post', $field,  array(
                'type'              => $type,
                'description'       => 'The ' . $field . ' of the book',
                'object_subtype'    => $this->post_type,
                'sanitize_callback' => $sanitize_callback,
                'required'          => true,
                'single'            => true,
                'show_in_rest'      => $show_in_rest,
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                }
            ) );
        }
    }

    /**
     * Validate the Book post type before insert.
     */
    public function rest_pre_insert_book( $prepared_post, $request ) {
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
     * Register meta boxes for the Book post type.
     */
    public function register_meta_boxes( $meta_boxes ) {
        $fields = array();

        foreach ( $this->meta_fields as $field ) {
            $type = 'text';
            $clone = false;

            if ( in_array( $field, array( 'languages', 'publish_places' ) ) ) {
                $type = 'array';
                $clone = true;
            }

            if ( in_array( $field, array( 'blurb', 'notes' ) ) ) {
                $type = 'textarea';
            }

            if ( in_array( $field, array( 'availability' ) ) ) {
                $type = 'select';
                $options = array(
                    'available'     => __( 'Available', 'pustaloka' ),
                    'borrowed'      => __( 'Borrowed', 'pustaloka' ),
                    'lost'          => __( 'Lost', 'pustaloka' ),
                    'lib_use_only'  => __( 'Library Use Only', 'pustaloka' ),
                );
            }

            if ( in_array( $field, array( 'number_of_pages', 'publish_year' ) ) ) {
                $type = 'integer';
            }

            if ( $field === 'library' ) {
                // post parent as library
                $fields[] = array(
                    'id'   => 'library',
                    'name' => __( 'Library', 'pustaloka' ),
                    'type' => 'post',
                    'parent' => true, // use post_parent as 'library
                    'post_type' => 'library',
                    'field_type' => 'select_advanced',
                    'placeholder' => __( 'Select a library', 'pustaloka' ),
                    'query_args' => array(
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'post_author' => -1,
                    ),
                );
            } else if ( $field === 'format' ) {
                $type = 'select';
                $options = array(
                    'paperback'     => __( 'Paperback', 'porabook' ),
                    'epub'          => __( 'EPUB', 'porabook' ),
                    'audiobook'     => __( 'Audiobook', 'porabook' ),
                    'manuscript'    => __( 'Manuscript', 'porabook' ),
                    'oversized'     => __( 'Oversized Books', 'porabook' ),
                    'hardcover'     => __( 'Hardcover', 'porabook' ),
                    'pdf'           => __( 'PDF', 'porabook' ),
                    'azw'           => __( 'AZW', 'porabook' ),
                    'mobi'          => __( 'Mobi', 'porabook' ),
                    'miniature'     => __( 'Miniature Books', 'porabook' ),
                    'ebook'         => __( 'E-Book', 'porabook' ),
                    'mass_market'   => __( 'Mass Market', 'porabook' ),
                    'fiction'       => __( 'Fiction Book', 'porabook' ),
                    'txt'           => __( 'TXT', 'porabook' ),
                );
            } else {
                $fields[] = array(
                    'id'    => $field,
                    'name'  => ucwords( str_replace( '_', ' ', $field ) ),
                    'type'  => $type,
                    'clone' => $clone,
                );
            }
        }

        $meta_boxes[] = array(
            'id'         => 'book_detail',
            'title'      => __( 'Book Detail', 'pustaloka' ),
            'post_types' => array( $this->post_type ),
            'context'    => 'side',
            'priority'   => 'default',
            'fields'     => $fields,
        );

        return $meta_boxes;
    }

    /**
     * Update metabox field before save.
     */
    public function rmbw_book_detail_before_save_post( $post_id ) {
        // set `library` value to `post_parent`
        if ( empty( $_POST['library'] ) ) {
            $_POST['library'] = $_POST['parent_id'];
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