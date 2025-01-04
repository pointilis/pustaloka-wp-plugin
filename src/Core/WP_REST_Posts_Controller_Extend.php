<?php

namespace Pointilis\Pustaloka\Core;

use Pointilis\Pustaloka\Core\Helpers;

class WP_REST_Posts_Controller_Extend extends \WP_REST_Posts_Controller {

    public function __construct( $post_type ) {
        parent::__construct( $post_type );

        add_filter( "rest_prepare_{$post_type}", array( $this, 'prepare_response' ), 10, 3 );
        add_filter( "rest_{$post_type}_query", array( $this, 'filter_query' ), 10, 2 );
        add_filter( "rest_pre_insert_{$post_type}", array( $this, 'prepare_for_item_database' ), 10, 2 );
        add_action( "rest_after_insert_{$post_type}", array( $this, 'after_insert' ), 10, 3 );
        add_filter( "rest_{$post_type}_item_schema", array( $this, 'rest_item_schema' ), 10, 1 );
    }

    /**
     * Custom schema for book
     */
    public function rest_item_schema( $schema ) {
        $schema['properties']['featured_media_url'] = array(
            'description' => __( 'Full featured media file url.', 'pustaloka' ),
            'type'        => 'url',
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        $schema['properties']['author'] = array(
            'description' => __( 'Author of the post.', 'pustaloka' ),
            'type'        => array( 'integer', 'object' ),
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

        $schema['properties']['latest_revision'] = array(
            'description' => __( 'Latest revision from the post.', 'pustaloka' ),
            'type'        => 'object',
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        // book only
        if ( $this->post_type === 'book' ) {
            // custom taxonomy book publisher
            $schema['properties']['book_publisher'] = array(
                'description' => __( 'Publishers for this book.', 'pustaloka' ),
                'type'        => 'array',
                'items'       => array( 'type' => array( 'string', 'integer' ) ),
                'context'     => array( 'view', 'edit' ),
                'readonly'    => true,
            );

            // custom taxonomy book author
            $schema['properties']['book_author'] = array(
                'description'   => __( 'Auhors for the book', 'pustaloka' ),
                'type'          => 'array',
                'items'         => array( 'type' => array( 'string', 'integer' ) ),
                'context'       => array( 'view', 'edit' ),
                'required'      => true,
            );
        }

        // Change tags item to string data type instead integer/number  
        $schema['properties']['tags']['items'] = array( 'type' => array( 'string', 'integer' ) );

        return $schema;
    }

    /**
     * Prepare item response.
     */
    public function prepare_response( $response, $post, $request ) {
        $fields = $this->get_fields_for_response( $request );

        // featured image
        if ( rest_is_field_included( 'featured_media_url', $fields ) ) {
            $featured_media_url = get_the_post_thumbnail_url( $book_id, 'large' );
            $default_image      = PUSTALOKA_URL . 'public/images/placeholder_book.png';
            $response->data['featured_media_url'] = $featured_media_url ? $featured_media_url : $default_image;
        }

        // post author
        if ( rest_is_field_included( 'author', $fields ) ) {
            $response->data['post_author'] = array(
                'id'   => $post->post_author,
                'name' => get_the_author_meta( 'display_name', $post->post_author ),
                'avatar_urls' => array(
                    'full'  => bp_core_fetch_avatar(
                        array(
                            'item_id' => $post->post_author,
                            'html'    => false,
                            'type'    => 'full',
                        )
                    ),
                    'thumb' => bp_core_fetch_avatar(
                        array(
                            'item_id' => $post->post_author,
                            'html'    => false,
                            'type'    => 'thumb',
                        )
                    ),
                ),
            );
        }

        // plain content
        if ( rest_is_field_included( 'content.plain_text', $fields ) ) {
            $response->data['content']['plain_text'] = wp_strip_all_tags( $post->post_content );
        }

        // tags
        if ( rest_is_field_included( 'tags', $fields ) ) {
            $response->data['tags'] = wp_get_post_tags( $post->ID );
        }

        // autosave count
        if ( rest_is_field_included( 'latest_revision', $fields ) ) {
            $response->data['latest_revision'] = wp_get_post_autosave( $post->ID );
        }

        // book post type
        if ( $this->post_type === 'book' ) {
            // book author
            if ( rest_is_field_included( 'book_author', $fields ) ) {
                $response->data['book_author_raw'] = wp_get_post_terms( $post->ID, 'book_author' );
            }

            // book publisher
            if ( rest_is_field_included( 'book_publisher', $fields ) ) {
                $response->data['book_publisher_raw'] = wp_get_post_terms( $post->ID, 'book_publisher' );
            }
        }

        // related to buddypress activity?
        $args = array( 'secondary_item_id' => $post->ID );
        $response->data['acivity_id'] = bp_activity_get_activity_id( $args );

        return $response;
    }

    /**
     * Collection params
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();

        $params['parent'] = array(
            'description' => __( 'Parent of the post.', 'pustaloka' ),
            'type'        => 'integer',
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        );

        return $params;
    }

    /**
     * More filters.
     */
    public function filter_query( $args, $request ) {
        $registered = $this->get_collection_params();

        if ( isset( $registered['parent'], $request['parent'] ) ) {
			$args['post_parent'] = $request['parent'];
		}

        // filter by meta_query
        // https://developer.wordpress.org/reference/classes/wp_meta_query/
        if ( $request->get_param( 'meta_query' ) ) {
            $meta_query = $request->get_param( 'meta_query' );
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * Prepare for database.
     */
    public function prepare_for_item_database( $prepared_post, $request ) {
        // Set book as post_parent
        // if ( isset( $request['meta']['book'] ) ) {
        //     $prepared_post->post_parent = (int) $request['meta']['book'];
        // }

        return $prepared_post;
    }

    /**
     * After inserted
     */
    public function after_insert( $post, $request, $is_new ) {
        if ( ! $is_new && $this->post_type === 'reading' ) {
            if ( $meta = $request->get_param( 'meta' ) ) {
                if ( isset( $meta['to_page' ] ) ) {
                    // mark as done if to_page >= book number_of_pages
                    $challenge_id = rwmb_get_value( 'challenge', array(), $post->ID );
                    $book_id = rwmb_get_value( 'book', array(), $challenge_id );
                    $number_of_pages = (int) rwmb_get_value( 'number_of_pages', array(), $book_id );

                    if ( (int) $meta['to_page'] >= $number_of_pages ) {
                        rwmb_set_meta( $challenge_id, 'status', 'done' );
                    }
                }
            }
        }
    }

}