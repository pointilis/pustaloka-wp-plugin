<?php

namespace Pointilis\Pustaloka\WP\PostType;

use Pointilis\Pustaloka\Core\Google_Cloud_Vision;
use Pointilis\Pustaloka\Core\Helpers;

class Attachment {

    private $helpers;

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_post_meta' ) );
        add_filter( 'rest_prepare_attachment', array( $this, 'prepare_attachment' ), 99, 3 );

        $this->helpers = new Helpers();
    }

    public function register_post_meta() {
        register_meta( 'post', 'is_book_page',  array(
            'type'              => 'boolean',
            'object_subtype'    => 'attachment',
            'required'          => false,
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => function() {
                return current_user_can( 'edit_posts' );
            }
        ) );
    }

    public function prepare_attachment( $response, $post, $request ) {
        if ( isset( $request['meta']['is_book_page'] ) ) {
            $summarized = '';

            $this->helpers->write_log( 'Uload attachment is book page.' );

            // Generate text from the image
            $vision = new Google_Cloud_Vision();
            $fullsize_path = get_attached_file( $post->ID ); // Full path
            $this->helpers->write_log( 'Retrieve media fullpath: ' . $fullsize_path . ' and start OCR.' );

            $output_string = $vision->detect_text( $fullsize_path );
            $this->helpers->write_log( 'OCR result: ' . $output_string );

            $output_string = trim( preg_replace('/\s+/', ' ', $output_string ) );
            $success = false;

            $this->helpers->write_log( 'Content length: ' . str_word_count( $output_string ) );
            
            if ( str_word_count( $output_string ) > 150 ) {
                // Summarize the text
                $this->helpers->write_log( 'Start summarize' );
                $summarized = $this->summarize( $output_string )['data'];
                $this->helpers->write_log( 'Summarize result: ' . $summarized );
                $success = true;
            } else {
                $this->helpers->write_log( 'Summarize not eligible.' );
                $summarized = $output_string;
            }

            // TODO: need schema for this
            $response->data['summarization'] = array(
                'raw' => $output_string,
                'rendered' => $summarized,
                'success' => $success,
            );
        }

        return $response;
    }

    /**
     * Send raw content to rest api and get back the summarize result
     * 
     * @param string $content
     * @return string
     */
    private function summarize( $content ) {
        $url = 'https://pustaloka-flask-app-rpvpoqorca-et.a.run.app/summarize/gemini';
        $response = wp_remote_post( $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(
                array(
                    'text' => $content,
                    'prompt' => 'As an expert writer with more than a decade of experience please summarize the text above. You are allowed to rephrase given the summary means the same as the original text. Please use original language from the original text.',
                )
            ),
        ] );

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

}