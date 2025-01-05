<?php

namespace Pointilis\Pustaloka\BP\XMembers;

use Pointilis\Pustaloka\Core\Helpers;

class BP_REST_Members_Endpoint extends \BP_REST_Members_Endpoint {

    /**
     * Register new route
     */
    public function register_routes() {
		// Register forgot password route.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/forgot-password',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'forgot_password' ),
					'permission_callback' => array( $this, 'forgot_password_permissions_check' ),
					'args'                => array(
						'user_email'     => array(
                            'description'       => __( 'User email used in registration', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        )
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

        // Register reset password route.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset-password',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_password' ),
					'permission_callback' => array( $this, 'reset_password_permissions_check' ),
					'args'                => array(
						'user_email'     => array(
                            'description'       => __( 'User email used in registration.', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        ),
                        'security_code'     => array(
                            'description'       => __( 'Security code send to email.', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        ),
                        'new_password'     => array(
                            'description'       => __( 'Set new password.', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        ),
                        'confirm_password'     => array(
                            'description'       => __( 'Confirm password.', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        ),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

        parent::register_routes();
    }

    /**
     * Perform forgot password, send secure code to email
     */
    public function forgot_password( $request ) {
        global $wpdb;

        $request->set_param( 'context', 'edit' );
        $user_email = $request->get_param( 'user_email' );
        $user = get_user_by( 'email', $user_email );

        // Check user exist with email
        if ( ! $user ) {
            return new \WP_Error(
				'bp_rest_check_user_validation_failed',
				__( 'User with that email not found.', 'pustaloka' ),
				array(
					'status' => 500,
				)
			);
        }

        // Save reset key to user meta
        $reset_key  = get_password_reset_key( $user );
        $code       = Helpers::generate_activation_key();
        update_user_meta( $user->data->ID, 'wp_reset_password_' . $code, $reset_key );

        // Sent code with email
        $args = array(
            'tokens' => array(
                'user.activation_key'   => $code,
                'user.email'            => $user_email,
                'user.id'               => $user->data->ID,
            ),
        );

        $send_email = bp_send_email( 'bp-members-forgot-password', $user_email, $args );

        if ( is_wp_error( $send_email ) ) {
            return new \WP_Error(
				'bp_rest_send_email_failed',
				$send_email->get_error_messages(),
				array(
					'status' => $send_email->get_error_code(),
				)
			);
        }

        $retval = array(
            'message' 		=> __( 'Reset password security key has been sent!', 'pustaloka' ),
			'user_email' 	=> $user_email,
        );

        $response = rest_ensure_response( $retval );
        return $response;
    }

    /**
     * Check email
     */
    public function forgot_password_permissions_check( $request ) {
        $user_email = $request->get_param( 'user_email' );

        if ( ! is_email( $user_email ) ) {
			return new \WP_Error(
				'bp_rest_check_email_validation_failed',
				__( 'Email invalid, please use correct email.', 'pustaloka' ),
				array(
					'status' => 500,
				)
			);
        }

        return true;
    }

    /**
     * Reset password
     */
    public function reset_password( $request ) {
        $user_email         = $request->get_param( 'user_email' );
        $security_code      = $request->get_param( 'security_code' );
        $new_password       = $request->get_param( 'new_password' );
        $confirm_password   = $request->get_param( 'confirm_password' );

        // Get user by email
        $user = get_user_by( 'email', $user_email );

        if ( ! $user ) {
            return new \WP_Error(
				'bp_rest_check_user_validation_failed',
				__( 'User with that email not found.', 'pustaloka' ),
				array(
					'status' => 500,
				)
			);
        }

        // Validate password
        if ( $new_password !== $confirm_password ) {
            return new \WP_Error(
				'bp_rest_check_password_mismatch',
				__( 'New password and confirmation password not matching.', 'pustaloka' ),
				array(
					'status' => 500,
				)
			);
        }
        
        // Get reset key from user meta
        $reset_key = get_user_meta( $user->ID, 'wp_reset_password_' . $security_code, true );

        // Validate security code
        $validate_security_code = check_password_reset_key( $reset_key, $user->user_login );
        
        if ( is_wp_error( $validate_security_code ) ) {
            return new \WP_Error(
				'bp_rest_check_security_code_failed',
				$validate_security_code->get_error_message(),
				array(
					'status'    => 500,
                    'code'      => $validate_security_code->get_error_code(),
				)
			);
        }

        // Set new password
        wp_set_password( $confirm_password, $user->ID );

        $retval = array(
            'message' 		=> __( 'Reset password success! Now login with new password.', 'pustaloka' ),
			'user_email' 	=> $user_email,
        );

        $response = rest_ensure_response( $retval );
        return $response;
    }

    /**
     * Check reset password permissions
     */
    public function reset_password_permissions_check( $request ) {
        return true;
    }

}