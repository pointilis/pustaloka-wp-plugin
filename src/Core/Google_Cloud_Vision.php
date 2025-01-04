<?php

namespace Pointilis\Pustaloka\Core;

use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision;

class Google_Cloud_Vision {

    private ImageAnnotatorClient $imageAnnotator;
    private string $accessToken = 'ya29.a0AXooCgukvOs029ze3atXorKkDFYXcY9N5GsG8pSnaGC-iaAHI0_z76QNUgG4CxKqbx_0L3JFOwnRFvSbBxq8KCfIo4KXUrqQqnUHBBLeHxAqEtp8An_kBS10o0aokUdw2CMinC5q0LfvdsBZgM0kjwflE14NeSEysfsd34LrPT4aCgYKAf4SARISFQHGX2Miv8P4YL4b6gyDz0zuze_tYQ0178';

    public function __construct() {
        $this->imageAnnotator = new ImageAnnotatorClient([
            'credentials' => PUSTALOKA_PATH . '/google_cloud_vision.json',
        ]);
    }

    /**
     * Detect text from local image
     * 
     * @param string $image_path
     * @return string
     */
    public function detect_text( string $image_path ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

        $filesystem = new \WP_Filesystem_Direct( true );
        $image = $filesystem->get_contents( $image_path );

        $response = $this->imageAnnotator->textDetection( $image, [ 'TEXT_DETECTION' ] );
        return \iterator_to_array( $response->getTextAnnotations() )[0]->getDescription();
    }

    /**
     * Detect text from image use REST API
     * 
     * @param string $image_path from local file
     * @return string
     */
    public function detect_text_rest(string $image_path) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

        $filesystem = new \WP_Filesystem_Direct( true );
        $image = $filesystem->get_contents( $image_path );

        $url = 'https://vision.googleapis.com/v1/images:annotate';
        $response = wp_remote_post( $url, [
            'body' => json_encode([
                'requests' => [
                    'image' => [
                        'content' => base64_encode( $image ),
                    ],
                    'features' => [
                        [
                            'type' => Type::TEXT_DETECTION,
                        ],
                    ],
                ],
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
                'x-goog-user-project' => 'pustaloka',
            ],
        ]);

        $responseBody = json_decode( wp_remote_retrieve_body( $response ) );
        return $responseBody->responses[0]->textAnnotations[0]->description;
    }

}