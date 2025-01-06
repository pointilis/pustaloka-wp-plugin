<?php

namespace Pointilis\Pustaloka\BP\XMembers;

use Pointilis\Pustaloka\Core\Helpers;
use Pointilis\Pustaloka\BP\XMembers\BP_Signup;
use Tmeister\Firebase\JWT\JWT;
use Tmeister\Firebase\JWT\Key;

class BP_REST_Signup_Endpoint extends \BP_REST_Signup_Endpoint {

	/**
	 * Generates a unique username.
	 *
	 * @param string $username Username to check.
	 * @return string username
	 */
	function generate_unique_username( $username ) {
		$username = sanitize_user( $username );

		static $i;
		if ( null === $i ) {
			$i = 1;
		} else {
			$i ++;
		}
		if ( ! username_exists( $username ) ) {
			return $username;
		}
		$new_username = sprintf( '%s-%s', $username, $i );
		if ( ! username_exists( $new_username ) ) {
			return $new_username;
		} else {
			return $this->generate_unique_username( $username );
		}
	}
    
    /**
     * Register new route
     */
    public function register_routes() {
		parent::register_routes();

		// Register the activate route.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/activate/(?P<activation_key>[\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'activate_item' ),
					'permission_callback' => array( $this, 'activate_item_permissions_check' ),
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

		// Register check oauth account like google, etc.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/oauth',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'oauth_create' ),
					'permission_callback' => array( $this, 'oauth_permissions_check' ),
					'args'                => array(
						'provider'     => array(
                            'description'       => __( 'Provider used for oauth like google, facebook, etc', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        ),
						'token_id'     => array(
                            'description'       => __( 'Security token', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        ),
						'profile_id'     => array(
                            'description'       => __( 'Profile id', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        ),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/resend-activation',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resend_activation' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
                        'user_email'     => array(
                            'description'       => __( 'User email will received activation key', 'pustaloka' ),
                            'type'              => 'string',
                            'context'           => array( 'edit', 'view' ),
                            'required'          => true,
                        )
                    ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
    }

    /**
     * Perform resend activation
     */
    public function resend_activation( $request ) {
        global $wpdb;

        $request->set_param( 'context', 'edit' );
        $user_email = $request->get_param( 'user_email' );

		// check email exist or not
		$email_found = $this->resend_activation_email_check( $user_email );

		if ( is_wp_error( $email_found ) ) {
			return $email_found;
		}

        $signup = $this->get_signup_object( $email_found );

        // Update activation key
        if ( ! empty( $signup ) ) {
            $signup->activation_key = Helpers::generate_activation_key();
            $update = $wpdb->update(
                buddypress()->members->table_name_signups,
                array(
                    'activation_key'    => $signup->activation_key,
                ),
                array(
                    'signup_id'     => $signup->id,
                    'user_email'    => $signup->user_email,
                    'active'        => 0,
				),
				array(
					'%s'
				),
				array(
					'%d',
					'%s',
					'%d'
				)
            );

            if ( is_wp_error( $update ) ) {
                return new \WP_Error(
                    'bp_rest_resend_activation_failed',
                    __( 'Something wrong please try again.', 'pustaloka' ),
                    array(
                        'status' => 500,
                    )
                );
            }
        }
        
        // Update cache
        wp_cache_replace( $signup->id, $signup, 'bp_signups' );

        // Perform resend
        $resend = \BP_Signup::resend( array( $signup->id ) );

        // Add feedback message.
        if ( ! empty( $resend['errors'] ) ) {
            $message = __( '<strong>Error</strong>: Your account has already been activated.', 'pustaloka' );
        } else {
            $message = __( 'Activation email resent! Please check your inbox or spam folder.', 'pustaloka' );
        }

        $retval = array(
            'message' 		=> $message,
			'user_email' 	=> $email_found,
        );

        $response = rest_ensure_response( $retval );
        return $response;
    }

    /**
     * Check email
     */
    public function resend_activation_email_check( $value ) {
        if ( ! is_email( $value ) ) {
			return new \WP_Error(
				'bp_rest_check_email_validation_failed',
				__( 'Email invalid, please use correct email.', 'pustaloka' ),
				array(
					'status' => 500,
				)
			);
        }

        // Check user already registered and active
        $signups = \BP_Signup::get( array( 
            'user_email'    => $value,
            'active'        => true,
        ) );

        if ( ! empty( $signups['signups'] ) && $signups['total'] > 0 ) {
            return new \WP_Error(
				'bp_rest_email_used',
				__( 'Your account has already been activated.', 'pustaloka' ),
				array(
					'status' => 400,
				)
			);
        }

        // Check the activation key is valid.
		if ( ! $this->get_signup_object( $value ) ) {
			return new \WP_Error(
                'bp_rest_email_not_found',
                __( 'Email not found.', 'buddypress' ),
                array(
                    'status' => 404,
                )
            );
		}
        
        return $value;
    }

	/**
	 * Oauth check based on provider
	 */
	public function oauth_create( $request ) {
		$profile_id 	= $request->get_param( 'profile_id' );
		$token 			= $request->get_param( 'token_id' );
		$provider 		= $request->get_param( 'provider' );
		$retval			= array();

		// for google user
		if ( $provider === 'google' ) {
			// check token is valid
			if ( $this->check_google_idtoken( $token ) ) {
				$users = get_users( array(
					'meta_key'		=> 'google_profile_id',
					'meta_value'	=> $profile_id,
					'number'		=> 1,
					'count_total'	=> false,
				) );

				// users not found
				if ( empty( $users ) ) {
					return new \WP_Error(
						'bp_rest_oauth_not_found',
						__( 'User not found', 'pustaloka' ),
						array(
							'status' => 404,
						)
					);
				}

				$user = reset( $users );
				$user = get_user_by( 'email', $user->user_email );

				// compare profile id with current
				$saved_profile_id = get_user_meta( $user->ID, 'google_profile_id', true );

				if ( ( $saved_profile_id !== $profile_id ) || ( $user->user_email !== $profile_id ) ) {
					return new \WP_Error(
						'bp_rest_oauth_not_found',
						__( 'User not found', 'pustaloka' ),
						array(
							'status' => 404,
						)
					);
				}

				// generate jwt token
				$secret_key = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : false;
				$issuedAt  	= time();
				$notBefore 	= apply_filters( 'jwt_auth_not_before', $issuedAt, $issuedAt );
				$expire    	= apply_filters( 'jwt_auth_expire', $issuedAt + ( DAY_IN_SECONDS * 7 ), $issuedAt );
				$token 		= array(
	                'iss'  => get_bloginfo( 'url' ),
                    'iat'  => $issuedAt,
	                'nbf'  => $notBefore,
                    'exp'  => $expire,
                    'data' => array(
	                    'user' => array(
	                    	'id' => $user->ID,
	                    ),
	                ),
				);
				$algorithm 	= apply_filters( 'jwt_auth_algorithm', 'HS256' );
				$token		= JWT::encode(
					apply_filters( 'jwt_auth_token_before_sign', $token, $user ),
					$secret_key,
					$algorithm
				);

				/** The token is signed, now create the object with no sensible user data to the client*/
	            $data = array(
	                'token'             => $token,
	                'user_email'        => $user->data->user_email,
	                'user_nicename'     => $user->data->user_nicename,
                	'user_display_name' => $user->data->display_name,
				);
	
	            /** Let the user modify the data before send it back */
	            $retval = apply_filters( 'jwt_auth_token_before_dispatch', $data, $user );
			}
		}

		// no retval return error
		if ( empty( $retval ) ) {
			return new \WP_Error(
				'bp_rest_oauth_failed',
				__( 'Data not complete yet!', 'pustaloka' ),
				array(
					'status' 	=> 500,
				)
			);
		}

		return rest_ensure_response( $retval );
	}

	public function oauth_permissions_check( $request ) {
		return true;
	}

	/**
	 * Oauth
	 */

    /**
     * Override create item
     */
    public function create_item( $request ) {
        $request->set_param( 'context', 'edit' );

		// Generate user_login from email
		$user_email 	= $request->get_param( 'user_email' );
		$id_token 		= $request->get_param( 'google_id_token' );
		$profile_id 	= $request->get_param( 'google_profile_id' );
		$username 		= substr( $user_email, 0, strrpos( $user_email, '@' ) );
		$username 		= $this->generate_unique_username( $username );

		// Set username as user_login
		$request->set_param( 'user_login', $username );
		$request->set_param( 'user_name', $username );

		// Validate user signup.
		$signup_validation = bp_core_validate_user_signup( $request->get_param( 'user_login' ), $request->get_param( 'user_email' ) );
		if ( is_wp_error( $signup_validation['errors'] ) && $signup_validation['errors']->get_error_messages() ) {
			// Return the first error.
			return new \WP_Error(
				'bp_rest_signup_validation_failed',
				$signup_validation['errors']->get_error_message(),
				array(
					'status' 	=> 500,
					'code'		=> $signup_validation['errors']->get_error_code(),
				)
			);
		}

		// Use the validated login and email.
		$user_login = $signup_validation['user_name'];
		$user_email = $signup_validation['user_email'];

		// Init the signup meta.
		$meta = array();

		// Init Some Multisite specific variables.
		$domain     = '';
		$path       = '';
		$site_title = '';
		$site_name  = '';

		if ( is_multisite() ) {
			$user_login    = preg_replace( '/\s+/', '', sanitize_user( $user_login, true ) );
			$user_email    = sanitize_email( $user_email );
			$wp_key_suffix = $user_email;

			if ( $this->is_blog_signup_allowed() ) {
				$site_title = $request->get_param( 'site_title' );
				$site_name  = $request->get_param( 'site_name' );

				if ( $site_title && $site_name ) {
					// Validate the blog signup.
					$blog_signup_validation = bp_core_validate_blog_signup( $site_name, $site_title );
					if ( is_wp_error( $blog_signup_validation['errors'] ) && $blog_signup_validation['errors']->get_error_messages() ) {
						// Return the first error.
						return new \WP_Error(
							'bp_rest_blog_signup_validation_failed',
							$blog_signup_validation['errors']->get_error_message(),
							array(
								'status' => 500,
							)
						);
					}

					$domain        = $blog_signup_validation['domain'];
					$wp_key_suffix = $domain;
					$path          = $blog_signup_validation['path'];
					$site_title    = $blog_signup_validation['blog_title'];
					$site_public   = (bool) $request->get_param( 'site_public' );

					$meta = array(
						'lang_id' => 1,
						'public'  => $site_public ? 1 : 0,
					);

					$site_language = $request->get_param( 'site_language' );
					$languages     = $this->get_available_languages();

					if ( in_array( $site_language, $languages, true ) ) {
						$language = wp_unslash( sanitize_text_field( $site_language ) );

						if ( $language ) {
							$meta['WPLANG'] = $language;
						}
					}
				}
			}
		}

		$password       = $request->get_param( 'password' );
		$check_password = $this->check_user_password( $password );

		if ( is_wp_error( $check_password ) ) {
			return $check_password;
		}

		// Hash and store the password.
		$meta['password'] = wp_hash_password( $password );

		$user_name = $request->get_param( 'user_name' );
		if ( $user_name ) {
			$meta['field_1']           = $user_name;
			$meta['profile_field_ids'] = 1;
		}

		if ( is_multisite() ) {
			// On Multisite, use the WordPress way to generate the activation key.
			$activation_key = substr( md5( time() . wp_rand() . $wp_key_suffix ), 0, 16 );

			if ( $site_title && $site_name ) {
				/** This filter is documented in wp-includes/ms-functions.php */
				$meta = apply_filters( 'signup_site_meta', $meta, $domain, $path, $site_title, $user_login, $user_email, $activation_key );
			} else {
				/** This filter is documented in wp-includes/ms-functions.php */
				$meta = apply_filters( 'signup_user_meta', $meta, $user_login, $user_email, $activation_key );
			}
		} else {
			$activation_key = wp_generate_password( 32, false );
		}

		/**
		 * Allow plugins to add their signup meta specific to the BP REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param array           $meta    The signup meta.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$meta = apply_filters( 'bp_rest_signup_create_item_meta', $meta, $request );

		$signup_args = array(
			'user_login'     => $user_login,
			'user_email'     => $user_email,
			'activation_key' => $activation_key,
			'domain'         => $domain,
			'path'           => $path,
			'title'          => $site_title,
			'meta'           => $meta,
		);

		// When register with google user will automatically activated.
		$direct_activation 	= false;
		$is_active			= false;

		// Validate google auth token.
		if ( $id_token ) {
			if ( $this->check_google_idtoken( $id_token ) ) {
				$direct_activation = true;
			} else {
				$direct_activation = false;

				return new \WP_Error(
					'bp_rest_signup_invalid_id_token',
					__( 'Cannot create new signup.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		}

		// Add signup.
		$id = BP_Signup::add( $signup_args );

		if ( ! is_numeric( $id ) ) {
			return new \WP_Error(
				'bp_rest_signup_cannot_create',
				__( 'Cannot create new signup.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$signup        = $this->get_signup_object( $id );
		$signup_update = $this->update_additional_fields_for_object( $signup, $request );

		if ( is_wp_error( $signup_update ) ) {
			return $signup_update;
		}

		if ( is_multisite() ) {
			if ( $site_title && $site_name ) {
				/** This action is documented in wp-includes/ms-functions.php */
				do_action( 'after_signup_site', $signup->domain, $signup->path, $signup->title, $signup->user_login, $signup->user_email, $signup->activation_key, $signup->meta );
			} else {
				/** This action is documented in wp-includes/ms-functions.php */
				do_action( 'after_signup_user', $signup->user_login, $signup->user_email, $signup->activation_key, $signup->meta );
			}
		} else {
			/** This filter is documented in bp-members/bp-members-functions.php */
			if ( apply_filters( 'bp_core_signup_send_activation_key', true, false, $signup->user_email, $signup->activation_key, $signup->meta ) ) {
				$salutation = $signup->user_login;
				if ( isset( $signup->user_name ) && $signup->user_name ) {
					$salutation = $signup->user_name;
				}

				bp_core_signup_send_validation_email( false, $signup->user_email, $signup->activation_key, $salutation );
			}
		}

		// All OK, now activate the user.
		if ( $direct_activation ) {
			$activated = bp_core_activate_signup( $signup->activation_key );
			if ( ! $activated ) {
				return new \WP_Error(
					'bp_rest_signup_activate_fail',
					__( 'Fail to activate the signup.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			} else {
				$is_active = true;
			}
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		// Inform activation status.
		$response->data[0]['is_active'] = $is_active;

		/**
		 * Fires after a signup item is created via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Signup        $signup   The created signup.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_signup_create_item', $signup, $response, $request );

		return $response;
    }

	/**
	 * Activate a signup.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_item( $request ) {
		$request->set_param( 'context', 'edit' );

		// Get the activation key.
		$activation_key = $request->get_param( 'activation_key' );
		$user_email = $request->get_param( 'user_email' );

		// Check signup email and activation email is match
		$signups = \BP_Signup::get( array( 'activation_key' => $activation_key ) );

		if ( ! empty( $signups['signups'] ) ) {
			$signup = reset( $signups['signups'] );

			// Check email
			if ( $user_email !== $signup->user_email ) {
				return new \WP_Error(
					'bp_rest_invalid_activation_email',
					__( 'Invalid activation email.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		}

		// Get the signup to activate thanks to the activation key.
		$signup    = $this->get_signup_object_validate_email( $activation_key, $user_email );
		$activated = bp_core_activate_signup( $activation_key );

		if ( ! $activated ) {
			return new \WP_Error(
				'bp_rest_signup_activate_fail',
				__( 'Fail to activate the signup.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a signup is activated via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Signup        $signup   The activated signup.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_signup_activate_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Get signup object.
	 *
	 * @since 6.0.0
	 *
	 * @param int|string $identifier Signup identifier.
	 * @return BP_Signup|false
	 */
	public function get_signup_object_validate_email( $identifier, $user_email ) {
		if ( is_numeric( $identifier ) ) {
			$signup_args['include'] = array( intval( $identifier ) );
		} elseif ( is_email( $identifier ) ) {
			$signup_args['usersearch'] = $identifier;
		} else {
			// The activation key is used when activating a signup.
			$signup_args['activation_key'] = $identifier;
			$signup_args['user_email'] = $user_email;
		}

		// Get signups.
		$signups = \BP_Signup::get( $signup_args );

		if ( ! empty( $signups['signups'] ) ) {
			return reset( $signups['signups'] );
		}

		return false;
	}

	/**
	 * Get the signup schema, conforming to JSON Schema.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = parent::get_item_schema();

		// make user_login not required
		$schema['properties']['user_login']['required'] = false;

		// added new schema for name
		$schema['properties']['display_name'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( 'The name of the user the signup is for.', 'buddypress' ),
			'required'    => true,
			'type'        => 'string',
		);

		return $schema;
	}

	/**
	 * Check google ID token
	 */
	public function check_google_idtoken( $id_token ) {
		$guzzleClient 	= new \GuzzleHttp\Client(array( 'curl' => array( CURLOPT_SSL_VERIFYPEER => false, ), ));
		$client_id 		= '1087260463503-80jh2sbb558fetnup7a09u91oevm4d8s.apps.googleusercontent.com';
		$client 		= new \Google_Client( array( 'client_id' => $client_id ) );
		$client->setHttpClient( $guzzleClient );
		$payload 		= $client->verifyIdToken( $id_token );

		return $payload;
	}

}