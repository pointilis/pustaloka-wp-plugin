<?php

namespace Pointilis\Pustaloka\WP\User;

class Register_Meta {

    public function register() {
        register_meta( 'user', 'google_idtoken', array(
            'type'              => 'string',
            'description'       => 'Google ID Token',
            'single'            => true,
            'show_in_rest'      => array(
                'prepare_callback' => function( $value ) {
                    return null;
                },
            
            ),
        ) );

        register_meta( 'user', 'google_user', array(
            'type'              => 'object',
            'description'       => 'Google User',
            'single'            => true,
            'show_in_rest'      => array(
                'schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'email' => array(
                            'type' => 'string',
                        ),
                        'name' => array(
                            'type' => 'string',
                        ),
                        'picture' => array(
                            'type' => 'string',
                        ),
                    ),
                ),
            ),
        ) );
    }   

}