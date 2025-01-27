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

        // Register stats route.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/stats',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'get_stats_permissions_check' ),
					'args'                => array(
                        'id' => array(
						    'description' => __( 'Unique identifier for the member.', 'buddypress' ),
						    'type'        => 'integer',
                            'required'    => true,
					    ),
                        'view' => array(
						    'description' => __( 'Type of stats want to view.', 'buddypress' ),
						    'type'        => 'string',
                            'required'    => true,
					    ),
                        'from_date' => array(
						    'description' => __( 'Filter by date.', 'buddypress' ),
						    'type'        => 'string',
                            'required'    => false,
                            'validate_callback' => function( $value ) {
                                $format = 'Y-m-d';
                                $d      = \DateTime::createFromFormat($format, $value);
                                return $d && strtolower( $d->format( $format ) ) === strtolower( $value );
                            },
					    ),
                        'to_date' => array(
						    'description' => __( 'Filter by date.', 'buddypress' ),
						    'type'        => 'string',
                            'required'    => false,
                            'validate_callback' => function( $value ) {
                                $format = 'Y-m-d';
                                $d      = \DateTime::createFromFormat($format, $value);
                                return $d && strtolower( $d->format( $format ) ) === strtolower( $value );
                            },
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

    /**
     * Get stats for user.
     */
    public function get_stats( $request ) {
        global $wpdb;
        
        $user_id    = $request->get_param( 'id' );
        $view       = $request->get_param( 'view' );
        $retval     = array();

        if ( $view === 'daily' || $view === 'pages_everyday' ) {
            $retval = $this->get_daily_stats( $request, $user_id );
        }

        if ( $view === 'book' ) {
            $retval = $this->get_book_stats( $request, $user_id );
        }

        if ( $view === 'general' ) {
            $retval = $this->get_general_stats( $request, $user_id );
        }
        
        return rest_ensure_response( $retval );
    }

    /**
     * Get stats permissions check
     */
    public function get_stats_permissions_check( $request ) {
        return true;
    }

    /**
     * Get total pages read every day
     */
    public function get_daily_stats( $request, $user_id ) {
        global $wpdb;
        
        $view               = $request->get_param( 'view' );
        $from_date          = $request->get_param( 'from_date' );
        $to_date            = $request->get_param( 'to_date' );
        $page               = $request->get_param( 'page' );
        $page               = $page ? $page : 1;
        $per_page           = 30;
        $offset             = ( $page * $per_page ) - $per_page;
        $from_page_key      = 'from_page';
        $to_page_key        = 'to_page';
        $to_datetime_key    = 'to_datetime';
        $from_datetime_key  = 'from_datetime';
        $pause_duration_key = 'pause_duration';
        $post_type          = 'reading';
        $post_status        = 'publish';
        $cache_key          = $view . '_' . $user_id;

        $select_sql = "
            SELECT      *,
                        SUM(from_page)  AS total_from_page,
                        SUM(to_page)    AS total_to_page,
                        SUM(to_page) - SUM(from_page) AS total_reading_page,
                        SUM(spending_time) AS spending_time,
                        SUM(pause_duration) AS pause_duration,
                        SUM(spending_time) - SUM(pause_duration) AS effective_duration

            FROM        (
                            SELECT      py.post_type,
                                        py.post_author,
                                        py.post_date,
                                        py.post_status,
                                
                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = py.ID
                                        ) AS from_page,

                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = py.ID
                                        ) AS to_page,

                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = py.ID
                                        ) AS to_datetime,

                                        TIME_TO_SEC(
                                            TIMEDIFF(
                                                (
                                                    SELECT      IFNULL(pm.meta_value, 0)
                                                    FROM        {$wpdb->postmeta} AS pm
                                                    WHERE       pm.meta_key = '%s'
                                                    AND         pm.post_id = py.ID
                                                ),
                                                (
                                                    SELECT      IFNULL(pm.meta_value, 0)
                                                    FROM        {$wpdb->postmeta} AS pm
                                                    WHERE       pm.meta_key = '%s'
                                                    AND         pm.post_id = py.ID
                                                )
                                            )
                                        ) AS spending_time,

                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = py.ID
                                        ) AS pause_duration

                            FROM        {$wpdb->posts} AS py
                        ) AS px
        ";

        $where_sql = "
            WHERE       px.post_type    = '%s'
            AND         px.post_status  = '%s'
            AND         px.post_author  = %d
        ";

        // filter by range date
        if ( $from_date && $to_date ) {
            $where_sql .= "AND DATE(to_datetime) BETWEEN '%s' AND '%s'";
        }

        $results = wp_cache_get( $cache_key );

        if ( $results === false) {
            $results = $wpdb->get_results( $wpdb->prepare(
                "
                {$select_sql}
                {$where_sql}
                GROUP BY DATE(to_datetime)
                LIMIT {$offset}, {$per_page}
                ",
                $from_page_key,
                $to_page_key,
                $to_datetime_key,
                $to_datetime_key,
                $from_datetime_key,
                $pause_duration_key,
                $post_type,
                $post_status,
                $user_id,
                $from_date,
                $to_date
            ) );

            wp_cache_set( $cache_key, $results );
        }

        return $results;
    }

    /**
     * Get books count in date range
     */
    public function get_book_stats( $request, $user_id ) {
        global $wpdb;

        $view               = $request->get_param( 'view' );
        $from_date          = $request->get_param( 'from_date' );
        $to_date            = $request->get_param( 'to_date' );
        $to_datetime_key    = 'to_datetime';
        $status_key         = 'status';
        $status_value       = 'done';
        $post_type          = 'challenge';
        $cache_key          = $view . '_' . $user_id;

        $select_sql = "
            SELECT      *,
                        DATE_FORMAT(px.to_datetime,'%Y-%m') AS to_date,
                        COUNT(px.to_datetime) AS total
            FROM        (
                            SELECT      py.post_type,
                                        py.post_author,
                                        py.post_status,
                                
                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = py.ID
                                        ) AS to_datetime,

                                        (
                                            SELECT      pm.meta_value
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = py.ID
                                        ) AS status

                            FROM        {$wpdb->posts} AS py
                        ) AS px
        ";

        $where_sql = "
            WHERE       px.post_type    = '%s'
            AND         px.post_status IN ('publish', 'draft')
            AND         px.post_author  = %d
            AND         px.status = '%s' 
        ";

        // filter by range date
        if ( $from_date && $to_date ) {
            $where_sql .= "AND px.to_datetime BETWEEN '%s' AND '%s'";
        }

        $results = wp_cache_get( $cache_key );

        if ( $results === false) {
            $results = $wpdb->get_results( $wpdb->prepare(
                "
                {$select_sql}
                {$where_sql}
                GROUP BY to_date
                ",
                $to_datetime_key,
                $status_key,
                $post_type,
                $user_id,
                $status_value,
                $from_date,
                $to_date
            ) );

            wp_cache_set( $cache_key, $results );
        }


        return $results;
    }

    /**
     * General stats
     */
    public function get_general_stats( $request, $user_id ) {
        global $wpdb;

        $from_date          = $request->get_param( 'from_date' );
        $to_date            = $request->get_param( 'to_date' );
        $from_page_key      = 'from_page';
        $to_page_key        = 'to_page';
        $to_datetime_key    = 'to_datetime';
        $challenge_key      = 'challenge';
        $from_datetime_key  = 'from_datetime';
        $pause_duration_key = 'pause_duration';
        $status_key         = 'status';
        $status_done_key    = 'done';
        $status_ongoing_key = 'ongoing';
        $post_type          = 'challenge';
        $post_status        = 'publish';

        $select_sql = "
            SELECT      SUM(to_page) - SUM(from_page) AS total_page,
                        SUM(spending_time) AS spending_time,
                        SUM(pause_duration) AS pause_duration,
                        COUNT(DISTINCT(pr.ID)) AS total_session,

                        (
                            SELECT      COUNT(DISTINCT(p.ID))
                            FROM        {$wpdb->posts} AS p
                            LEFT JOIN   {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
                            WHERE       p.post_type = '%s'
                            AND         p.post_status = '%s'
                            AND         p.post_author = %d
                            AND         pm.meta_key = '%s' AND pm.meta_value = '%s'
                        ) AS challenge_done,

                        (
                            SELECT      COUNT(DISTINCT(p.ID))
                            FROM        {$wpdb->posts} AS p
                            LEFT JOIN   {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
                            WHERE       p.post_type = '%s'
                            AND         p.post_status = '%s'
                            AND         p.post_author = %d
                            AND         pm.meta_key = '%s' AND pm.meta_value = '%s'
                        ) AS challenge_ongoing

            FROM        (
                            SELECT      p.ID,
                                        p.post_type,
                                        p.post_author,
                                        p.post_status,
                                
                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = p.ID
                                        ) AS from_page,

                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = p.ID
                                        ) AS to_page,

                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = p.ID
                                        ) AS challenge,

                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = p.ID
                                        ) AS to_datetime,

                                        TIME_TO_SEC(
                                            TIMEDIFF(
                                                (
                                                    SELECT      IFNULL(pm.meta_value, 0)
                                                    FROM        {$wpdb->postmeta} AS pm
                                                    WHERE       pm.meta_key = '%s'
                                                    AND         pm.post_id = p.ID
                                                ),
                                                (
                                                    SELECT      IFNULL(pm.meta_value, 0)
                                                    FROM        {$wpdb->postmeta} AS pm
                                                    WHERE       pm.meta_key = '%s'
                                                    AND         pm.post_id = p.ID
                                                )
                                            )
                                        ) AS spending_time,

                                        (
                                            SELECT      IFNULL(pm.meta_value, 0)
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = p.ID
                                        ) AS pause_duration

                            FROM        {$wpdb->posts} AS p
                        ) AS pr

            LEFT JOIN   (
                            SELECT      p.ID,
                                        p.post_author,
                                        p.post_type,

                                        (
                                            SELECT      pm.meta_value
                                            FROM        {$wpdb->postmeta} AS pm
                                            WHERE       pm.meta_key = '%s'
                                            AND         pm.post_id = p.ID
                                        ) AS status
                    
                            FROM        {$wpdb->posts} AS p
                        ) AS pc ON pc.ID = pr.challenge
        ";

        $where_sql = "
            WHERE       pc.post_author = %d
            AND         pc.post_type = '%s'
            AND         pc.status IN ('ongoing', 'done')
        ";

        // filter by range date
        if ( $from_date && $to_date ) {
           $where_sql .= "AND DATE(to_datetime) BETWEEN '%s' AND '%s'";
        }

        $result = $wpdb->get_row( $wpdb->prepare(
            "
            {$select_sql}
            {$where_sql}
            GROUP BY pc.post_author
            ",
            // challenge done
            $post_type,
            $post_status,
            $user_id,
            $status_key,
            $status_done_key,

            // challenge ongoing
            $post_type,
            $post_status,
            $user_id,
            $status_key,
            $status_ongoing_key,
            
            $from_page_key,
            $to_page_key,
            $challenge_key,
            $to_datetime_key,
            $to_datetime_key, // for time spend
            $from_datetime_key, // for time spend
            $pause_duration_key, // pause duration
            $status_key,

            $user_id,
            $post_type,

            $from_date,
            $to_date
        ) );

        return array(
            'user_id'           => $user_id,
            'total_page'        => $result ? (float) $result->total_page : 0,
            'total_session'     => $result ? (float) $result->total_session : 0,
            'spending_time'     => $result ? (float) round( $result->spending_time ) : 0,
            'pause_duration'    => $result ? (float) round( $result->pause_duration ) : 0,
            'challenge_done'    => $result ? (float) $result->challenge_done : 0,
            'challenge_ongoing' => $result ? (float) $result->challenge_ongoing : 0,
        );
    }

}