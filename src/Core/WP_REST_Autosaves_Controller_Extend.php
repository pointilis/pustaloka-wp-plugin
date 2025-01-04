<?php

namespace Pointilis\Pustaloka\Core;

use Pointilis\Pustaloka\Core\WP_REST_Posts_Controller_Extend;
use Pointilis\Pustaloka\Core\WP_REST_Revisions_Controller_Extend;

class WP_REST_Autosaves_Controller_Extend extends \WP_REST_Autosaves_Controller {

    protected $meta;

    public function __construct( $parent_post_type ) {
        parent::__construct( $parent_post_type );

        $this->parent_post_type = $parent_post_type;
        $post_type_object       = get_post_type_object( $parent_post_type );
        $parent_controller      = $post_type_object->get_rest_controller();

        if ( ! $parent_controller ) {
            $parent_controller = new WP_REST_Posts_Controller_Extend( $parent_post_type );
        }

        $this->parent_controller = $parent_controller;

        $revisions_controller = $post_type_object->get_revisions_rest_controller();
		if ( ! $revisions_controller ) {
			$revisions_controller = new WP_REST_Revisions_Controller_Extend( $parent_post_type );
		}
		$this->revisions_controller = $revisions_controller;

        // Define update meta for revision
        $this->meta = new \WP_REST_Post_Meta_Fields( $parent_post_type );

        // added more date to response
        add_filter( "rest_prepare_autosave", array( $this, 'prepare_response' ), 10, 3 );
        add_filter( "rest_{$parent_post_type}_item_schema", array( $this, 'item_schema' ), 10, 1 ); 
    }

    /**
	 * Creates, updates or deletes an autosave revision.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {

		if ( ! defined( 'WP_RUN_CORE_TESTS' ) && ! defined( 'DOING_AUTOSAVE' ) ) {
			define( 'DOING_AUTOSAVE', true );
		}

		$post = $this->get_parent( $request['id'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$prepared_post     = $this->parent_controller->prepare_item_for_database( $request );
		$prepared_post->ID = $post->ID;
		$user_id           = get_current_user_id();

		// We need to check post lock to ensure the original author didn't leave their browser tab open.
		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$post_lock = wp_check_post_lock( $post->ID );
		$is_draft  = 'draft' === $post->post_status || 'auto-draft' === $post->post_status;

		if ( $is_draft && (int) $post->post_author === $user_id && ! $post_lock ) {
			/*
			 * Draft posts for the same author: autosaving updates the post and does not create a revision.
			 * Convert the post object to an array and add slashes, wp_update_post() expects escaped array.
			 */
			$autosave_id = wp_update_post( wp_slash( (array) $prepared_post ), true );
		} else {
			// Non-draft posts: create or update the post autosave. Pass the meta data.
			$autosave_id = $this->create_post_autosave( (array) $prepared_post, (array) $request->get_param( 'meta' ) );
		}

		if ( is_wp_error( $autosave_id ) ) {
			return $autosave_id;
		}

        // Custom handling for schema.
        // by default this not supported in WP core
        $this->handle_schema_from_parent( $autosave_id, $request );

		$autosave = get_post( $autosave_id );
		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $autosave, $request );
		$response = rest_ensure_response( $response );

		return $response;
	}

    /**
	 * Determines the featured media based on a request param.
	 *
	 * @since 4.7.0
	 *
	 * @param int $featured_media Featured Media ID.
	 * @param int $post_id        Post ID.
	 * @return bool|WP_Error Whether the post thumbnail was successfully deleted, otherwise WP_Error.
	 */
    function handle_featured_media( $featured_media, $post_id ) {
		$featured_media = (int) $featured_media;
        
		if ( $featured_media ) {
			$result = set_post_thumbnail( $post_id, $featured_media );
			if ( $result ) {
				return true;
			} else {
				return new \WP_Error(
					'rest_invalid_featured_media',
					__( 'Invalid featured media ID.' ),
					array( 'status' => 400 )
				);
			}
		} else {
			return delete_post_thumbnail( $post_id );
		}
	}

    /**
	 * Updates the post's terms from a REST request.
	 *
	 * @since 4.7.0
	 *
	 * @param int             $post_id The post ID to update the terms form.
	 * @param WP_REST_Request $request The request object with post and terms data.
	 * @return null|WP_Error WP_Error on an error assigning any of the terms, otherwise null.
	 */
	protected function handle_terms( $post_id, $request ) {
		$taxonomies = wp_list_filter( get_object_taxonomies( $this->parent_post_type, 'objects' ), array( 'show_in_rest' => true ) );

		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

			if ( ! isset( $request[ $base ] ) ) {
				continue;
			}

			$result = wp_set_object_terms( $post_id, $request[ $base ], $taxonomy->name );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
	}

    /**
     * Handle custom schema from parent post type
     */
    public function handle_schema_from_parent( $autosave_id, $request) {
        $schema = $this->get_item_schema();

        if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
            $meta_update = $this->meta->update_value( $request['meta'], $autosave_id );

            if ( is_wp_error( $meta_update ) ) {
                return $meta_update;
            }
        }

        if ( ! empty( $schema['properties']['featured_media'] ) && isset( $request['featured_media'] ) ) {
            $this->handle_featured_media( $request['featured_media'], $autosave_id );
        }

        $terms_update = $this->handle_terms( $autosave_id, $request );

		if ( is_wp_error( $terms_update ) ) {
			return $terms_update;
		}
    }

    /**
     * Extend item schema
     */
    public function item_schema( $schema ) {
        $schema['properties']['featured_media_url'] = array(
            'description' => __( 'Full featured media file url.', 'pustaloka' ),
            'type'        => 'url',
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        $schema['properties']['content']['plain_text'] = array(
            'description' => __( 'Content without HTML tags.', 'pustaloka' ),
            'type'        => 'string',
            'items'       => array( 'type' => 'string' ),
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        $schema['properties']['tags'] = array(
            'description' => __( 'Tags of the post.', 'pustaloka' ),
            'type'        => 'array',
            'items'       => array( 'type' => 'string' ),
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        // custom taxonomy book_author
        $schema['properties']['book_author'] = array(
            'description' => __( 'Authors for this book.', 'pustaloka' ),
            'type'        => 'array',
            'items'       => array( 'type' => 'string' ),
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        // custom taxonomy book_publisher
        $schema['properties']['book_publisher'] = array(
            'description' => __( 'Publishers for this book.', 'pustaloka' ),
            'type'        => 'array',
            'items'       => array( 'type' => 'string' ),
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        return $schema;
    }

    /**
     * Prepare response for autosave
     */
    public function prepare_response( $response, $post, $request ) {
        $parent_id = wp_get_post_parent_id( $post->ID );
        $fields = $this->parent_controller->get_fields_for_response( $request );

        // featured image
        if ( rest_is_field_included( 'featured_media_url', $fields ) ) {
            $featured_media_url = get_the_post_thumbnail_url( $parent_id, 'large' );
            $default_image = plugin_dir_url( dirname( __DIR__ ) ) . 'public/images/No-Image-Placeholder.png';
            $response->data['featured_media_url'] = $featured_media_url ? $featured_media_url : $default_image;
        }

        // plain content
        if ( rest_is_field_included( 'content.plain_text', $fields ) ) {
            $response->data['content']['plain_text'] = wp_strip_all_tags( $post->post_content );
        }

        // tags
        if ( rest_is_field_included( 'tags', $fields ) ) {
            $response->data['tags'] = wp_get_post_tags( $post->ID );
        }

        // book author
        if ( rest_is_field_included( 'book_author', $fields ) ) {
            $response->data['book_author'] = wp_get_post_terms( $post->ID, 'book_author' );
        }

        // book publisher
        if ( rest_is_field_included( 'book_publisher', $fields ) ) {
            $response->data['book_publisher'] = wp_get_post_terms( $post->ID, 'book_publisher' );
        }
 
        return $response;
    }

}