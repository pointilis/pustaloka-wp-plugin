<?php

namespace Pointilis\Pustaloka\Core;

class WP_REST_Users_Controller_Extend extends \WP_REST_Users_Controller {

    private $google_api_client;
    private $google_user;

    public function __construct() {
        parent::__construct();

        $this->google_api_client = new Google_API_Client();

        add_action( 'rest_prepare_user', array( $this, 'prepare_user' ), 10, 3 );
        add_action( 'rest_after_insert_user', array( $this, 'after_insert_user' ), 10, 3 );
        add_action( 'rest_insert_user', array( $this, 'insert_user' ), 10, 3 );
        add_filter( 'rest_pre_insert_user', array( $this, 'pre_insert_user' ), 10, 2 );
    }

    /**
	 * Checks if a given request has access create users.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
        $roles      = $request->get_param( 'roles' );
        $allowed    = array( 'author', 'subscriber' );
        $passed     = array();

        if ( ! empty( $roles ) ) {
            $passed = array_intersect( $roles, $allowed );
        }

        // Set back to roles to default if not allowed
        if ( ! empty( $passed ) ) {
            $request->set_param( 'roles', $passed );
        }

		if ( empty( $passed ) && ! current_user_can( 'create_users' ) ) {
			return new \WP_Error(
				'rest_cannot_create_user',
				__( 'Sorry, you are not allowed to create new users.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

        $this->google_user = $this->validate_id_token( $request );
        if ( is_wp_error( $this->google_user ) ) {
            return $this->google_user;
        }

		return true;
	}

    /**
     * Validate ID Token from google login
     * 
     * @Param string $id_token
     */
    private function validate_id_token( $request ) {
        if ( ! empty( $request['meta'] ) && ! empty( $request['meta']['google_idtoken'] ) ) {
            // Validate google idToken from client
            $google_idtoken = $request['meta']['google_idtoken'];
            $google_user = $this->google_api_client->validate_id_token( $google_idtoken );

            if ( empty( $google_user ) || ! isset( $google_user['email'] ) ) {
                return new \WP_Error(
                    'rest_invalid_google_idtoken',
                    __( 'Invalid Google ID Token.' ),
                    array( 'status' => 400 )
                );
            }

            return $google_user;
        } else {
            // ID token empty
            return new \WP_Error(
                'rest_empty_google_idtoken',
                __( 'Google ID Token is required.' ),
                array( 'status' => 400 )
            );
        }
    }

    /**
	 * Updates a single user.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$user = $this->get_user( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$id = $user->ID;

		$owner_id = false;
		if ( is_string( $request['email'] ) ) {
			$owner_id = email_exists( $request['email'] );
		}

		if ( $owner_id && $owner_id !== $id ) {
			return new \WP_Error(
				'rest_user_invalid_email',
				__( 'Invalid email address.' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $request['slug'] ) && $request['slug'] !== $user->user_nicename && get_user_by( 'slug', $request['slug'] ) ) {
			return new \WP_Error(
				'rest_user_invalid_slug',
				__( 'Invalid slug.' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $request['roles'] ) ) {
			$check_permission = $this->check_role_update( $id, $request['roles'] );

			if ( is_wp_error( $check_permission ) ) {
				return $check_permission;
			}
		}

        // this username must defined before changed by prepare user for database
        $username = $user->user_login;
		$user = $this->prepare_item_for_database( $request );

		// Ensure we're operating on the same user we already checked.
		$user->ID = $id;

        if ( ! empty( $request['username'] ) && $request['username'] !== $username ) {
            // update username
            // by default wp not allowed user to update username
            $user_id = $this->update_username( $request['username'], $user );
        } else {
		    $user_id = wp_update_user( wp_slash( (array) $user ) );
        }

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', $user_id );
        clean_user_cache( $user );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php */
		do_action( 'rest_insert_user', $user, $request, false );

		if ( ! empty( $request['roles'] ) ) {
			array_map( array( $user, 'add_role' ), $request['roles'] );
		}

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$user          = get_user_by( 'id', $user_id );
		$fields_update = $this->update_additional_fields_for_object( $user, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php */
		do_action( 'rest_after_insert_user', $user, $request, false );

		$response = $this->prepare_item_for_response( $user, $request );
		$response = rest_ensure_response( $response );

		return $response;
	}

    /**
     * Prepare user for response.
     */
    public function prepare_user( $response, $user, $request ) {
        $response->data['username'] = $user->user_login;
        $response->data['email'] = $user->user_email;
        
        return $response;
    }

    /**
     * Prepare user for database.
     */
    public function pre_insert_user( $prepared_user, $request ) {
        return $prepared_user;
    }

    /**
     * Item schema.
     */
    public function get_item_schema() {
        if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = parent::get_item_schema();

        if ( get_option( 'users_can_register' ) ) {
            // Roles is required.
            $schema['properties']['roles']['required'] = true;
            $schema['properties']['roles']['context'] = array( 'view', 'edit' );
        }

        $schema['properties']['username']['context'] = array( 'view', 'edit' );

        $this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
    }

    /**
     * Update username
     * by default wp not allowed user to update username
     * 
     * @param string $username
     */
    private function update_username( $username, $user ) {
        global $wpdb;

        $sanitized_user_login = sanitize_user( $username, true );

        /**
         * Filters a username after it has been sanitized.
         *
         * This filter is called before the user is created or updated.
         *
         * @since 2.0.3
         *
         * @param string $sanitized_user_login Username after it has been sanitized.
         */
        $pre_user_login = apply_filters( 'pre_user_login', $sanitized_user_login );

        // Remove any non-printable chars from the login string to see if we have ended up with an empty username.
        $user_login = trim( $pre_user_login );

        // user_login must be between 0 and 60 characters.
        if ( empty( $user_login ) ) {
            return new \WP_Error( 'empty_user_login', __( 'Cannot create a user with an empty login name.' ) );
        } elseif ( mb_strlen( $user_login ) > 60 ) {
            return new \WP_Error( 'user_login_too_long', __( 'Username may not be longer than 60 characters.' ) );
        }

        // user_login must not have any white space
        if ( preg_match( '/\s/', $user_login ) ) { 
            return new \WP_Error( 'user_login_white_space', __( 'Username may not have any white space.' ) );
        }

        if ( username_exists( $user_login ) ) {
            return new \WP_Error( 'existing_user_login', __( 'Sorry, that username already exists!' ) );
        }

        // update username here
        $update = $wpdb->update(
            $wpdb->users,
            array(
                'user_login'    => $user_login,
                'user_nicename' => $user_login,
            ),
            array(
                'ID'    => $user->ID,
            ),
            array(
                '%s',
                '%s'
            ),
            array(
                '%d'
            )
        );

        if ( is_wp_error( $update ) ) {
            return new \WP_Error(
                'rest_user_change_username',
                __( 'Change username failed.' ),
                array( 'status' => 400 )
            );
        }
        
        return $user->ID;
    }

    /**
     * After user inserted
     */
    public function after_insert_user( $user, $request, $is_new ) {
        if ( $is_new ) {
            // store google user into user meta
            $google_user = array(
                'email'     => $this->google_user['email'],
                'name'      => $this->google_user['name'],
                'picture'   => $this->google_user['picture'],
            );

            $meta_update = $this->meta->update_value( array('google_user' => $google_user ), $user->ID );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}

            // clear current user
            wp_set_current_user( 0 );
        }
    }

    /**
     * On insert user
     */
    public function insert_user( $user, $request, $is_new ) {
        // Set temporary user for update user meta
        // must removed after user inserted
        if ( $is_new ) {
            wp_set_current_user( $user->ID );
        }
    }

    /**
	 * Creates a single user.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
        $owner_id = false;
        
        if ( is_string( $request['email'] ) ) {
			$owner_id = email_exists( $request['email'] );
		}

        // Register user
        if ( ! $owner_id ) {
            return parent::create_item( $request );
        }
        
        // Return user
        $request->set_param( 'id', $owner_id );
        return $this->get_item( $request );
    }

}