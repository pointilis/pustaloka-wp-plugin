<?php

namespace Pointilis\Pustaloka\Core;

use Pointilis\Pustaloka\Core\WP_REST_Posts_Controller_Extend;

class WP_REST_Revisions_Controller_Extend extends \WP_REST_Revisions_Controller {

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

        $this->meta = new \WP_REST_Post_Meta_Fields( $parent_post_type );

        // add_filter( "rest_{$parent_post_type}_item_schema", array( $this, 'item_schema' ), 10, 1 ); 

        // added more date to response
        add_filter( "rest_prepare_revision", array( $this, 'prepare_response' ), 10, 3 );
    }

    public function item_schema( $schema ) {
        $parent_schema = $this->parent_controller->get_item_schema();

        // custom taxonomy book author
        if ( ! empty( $parent_schema['properties']['book_author'] ) ) {
            $schema['properties']['book_author'] = $parent_schema['properties']['book_author'];
        }

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