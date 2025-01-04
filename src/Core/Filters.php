<?php 

namespace Pointilis\Pustaloka\Core;

class Filters {

    public function __construct() {
        add_filter( 'the_content', array( $this, 'wpdocs_remove_shortcode_from_index' ) );
        add_filter( 'jwt_auth_token_before_dispatch', array( $this, 'jwt_auth_token_before_dispatch_extend' ), 10, 2 );
        add_filter( 'rest_allowed_cors_headers', array( $this, 'add_cors_headers' ), 10, 2 );
        // add_filter( 'rest_prepare_revision', array( $this, 'prepare_autosave_response' ), 10, 3 );
        // add_filter( 'rest_prepare_autosave', array( $this, 'prepare_autosave_response' ), 10, 3 );
        add_filter( 'rest_api_init', array( $this, 'add_custom_headers' ), 15 );
    }

    /**
     * Remove figure from content.
     */
    public function wpdocs_remove_shortcode_from_index( $content ) {
        if ( defined( 'REST_REQUEST' ) ) {
            $content = preg_replace("/(<figure.*?[^>]*>)(.*?)(<\/figure>)/i", "", $content);
        }

        return $content;
    }

    /**
     * Modify JWT token response
     */
    public function jwt_auth_token_before_dispatch_extend( $data, $user ) {
        $user_id = $user->ID;

        $data['user_id'] = $user_id;
        $data['user_login'] = $user->user_login;

        // Set auth cookie for HTTP Only authentication
        // wp_set_auth_cookie( $user_id, true, true );

        return $data;
    }

    public function add_cors_headers( $headers, $request ) {
        $headers[] = 'X-WP-Nonce';
        $headers[] = 'X-WP-Auto-Save';

        return $headers;
    }

    public function add_custom_headers( $value ) {
        remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

        add_filter( 'rest_pre_serve_request', function( $value ) {
            header( 'X-WP-Auto-Save: 1' );

            rest_send_cors_headers( $value );
            return $value;
            
        });
    }

    /**
     * Prepare response for revision
     */
    public function prepare_autosave_response( $response, $post, $request ) {
        $parent = wp_get_post_parent_id( $post->ID );

        // featured image
        $featured_media_url = get_the_post_thumbnail_url( $parent, 'large' );
        $default_image = plugin_dir_url( dirname( __DIR__ ) ) . 'public/images/No-Image-Placeholder.png';
        $response->data['featured_media_url'] = $featured_media_url ? $featured_media_url : $default_image;

        // plain content
        $response->data['content']['plain_text'] = wp_strip_all_tags( $post->post_content );

        // tags lists
        $response->data['tags_list'] = wp_get_post_tags( $parent );
 
        return $response;
    }

}