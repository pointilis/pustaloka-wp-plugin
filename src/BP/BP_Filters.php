<?php

namespace Pointilis\Pustaloka\BP;

use Pointilis\Pustaloka\Core\Helpers;

class BP_Filters {

    public function __construct() {
        add_filter( 'bp_after_bp_core_signups_add_args_parse_args', array( $this, 'bp_core_signups_add_args_extend' ), 10, 1 );
        add_filter( 'bp_rest_signup_create_item_meta', array( $this, 'bp_rest_signup_create_item_meta_extend' ), 10, 2 );
        add_filter( 'bp_email_get_unsubscribe_type_schema', array( $this, 'bp_email_get_unsubscribe_type_schema_extend' ), 10, 1 );
        add_filter( 'bp_email_get_schema', array( $this, 'bp_email_get_schema_extend' ), 10, 1 );
        add_filter( 'bp_email_set_tokens', array( $this, 'bp_email_set_tokens_extend' ), 10, 3 );
        add_filter( 'bp_rest_friends_prepare_value', array( $this, 'bp_rest_friends_prepare_value_extend' ), 10, 3 );
        add_filter( 'bp_rest_activity_prepare_value', array( $this, 'bp_rest_activity_prepare_value_extend' ), 10, 3 );
        add_filter( 'bp_rest_members_prepare_value', array( $this, 'bp_rest_members_prepare_value_extend' ), 10, 3 );
        add_action( 'bp_core_activated_user', array( $this, 'bp_core_activated_user_extend' ), 10, 3 ); 
        add_action( 'bp_core_install_emails', array( $this, 'bp_core_install_emails_extend' ) );
        add_filter( 'bp_after_bp_get_user_friendships', array( $this, 'bp_after_bp_get_user_friendships' ), 10, 1 );
        add_filter( 'bp_rest_friends_get_items_query_args', array( $this, 'bp_rest_friends_get_items_query_args' ), 10, 2 );
    }

    /**
     * Modify some field for registration
     * 
     * @type array $retval  see BP_Signup->add()
     */
    public function bp_core_signups_add_args_extend( $retval ) {
        // Replace activation key with sort number like OTP
        $retval['activation_key'] = Helpers::generate_activation_key();

        return $retval;
    }

    /**
     * Custom get friendship parameters
     */
    public function bp_after_bp_get_user_friendships( $retval ) {
        $retval['show_as'] = null;

        return $retval;
    }

    /**
     * Custom get friendships query
     */
    public function bp_rest_friends_get_items_query_args( $args, $request ) {
        $args['show_as'] = $request->get_param( 'show_as', 'friend' ); // default is `friend`

        return $args;
    }

    /**
     * After user activated.
     */
    public function bp_core_activated_user_extend( $user_id, $key, $user ) {
        // set user role as author
        $u = new \WP_User( $user_id );
        $u->set_role( 'author' );

        // update user with custom meta value
        $meta = $user['meta'];
        
        if ( isset( $meta['display_name'] ) ) {
            $new_data = array( 'ID' => $user_id, 'display_name' => $meta['display_name'] );
            wp_update_user( $new_data );
        }

        // save access token
        if ( isset( $meta['google_access_token'] ) ) {
            update_user_meta( $user_id, 'google_access_token', $meta['google_access_token'] );
        }

        // save id token
        if ( isset( $meta['google_id_token'] ) ) {
            update_user_meta( $user_id, 'google_id_token', $meta['google_id_token'] );
        }

        // save profile id
        if ( isset( $meta['google_profile_id'] ) ) {
            update_user_meta( $user_id, 'google_profile_id', $meta['google_profile_id'] );
        }
        
    }

    /**
     * Add more meta data for registration purpose
     */
    public function bp_rest_signup_create_item_meta_extend( $meta, $request ) {
        $display_name = $request->get_param( 'display_name' );
        $google_access_token = $request->get_param( 'google_access_token' );
        $google_id_token = $request->get_param( 'google_id_token' );
        $google_profile_id = $request->get_param( 'google_profile_id' );

        // add display name
        $meta['display_name'] = $display_name;

        // login with google
        $meta['google_access_token'] = $google_access_token;
        $meta['google_id_token'] = $google_id_token;
        $meta['google_profile_id'] = $google_profile_id;

        return $meta;
    }

    /**
     * Custom email schemas
     */
    public function bp_email_get_unsubscribe_type_schema_extend( $emails ) {
        $forgot_password = array(
            'description'	   => __( 'Send security code for lost password.', 'pustaloka' ),
            'named_salutation' => false,
		    'unsubscribe'	   => false,
        );

        $emails['bp-members-forgot-password'] = $forgot_password;

        return $emails;
    }

    /**
     * Define custome schema
     */
    public function bp_email_get_schema_extend( $schemas ) {
        $schemas['bp-members-forgot-password'] = array(
			/* translators: do not remove {} brackets or translate its contents. */
			'post_title'   => __( '[{{{site.name}}}] Reset password security code', 'pustaloka' ),
			/* translators: do not remove {} brackets or translate its contents. */
			'post_content' => __( "You recently request reset password with your account on {{site.name}}.\n\nYour security code is: {{user.activation_key}}\n\nOtherwise, you can safely ignore and delete this email if you have changed your mind, or if you think you have received this email in error.", 'pustaloka' ),
			/* translators: do not remove {} brackets or translate its contents. */
			'post_excerpt' => __( "You recently request reset password with your account on {{site.name}}.\n\nYour security code is: {{user.activation_key}}\n\nOtherwise, you can safely ignore and delete this email if you have changed your mind, or if you think you have received this email in error.", 'pustaloka' ),
		);

        return $schemas;
    }

    /**
     * Fire after email re-installed
     */
    public function bp_core_install_emails_extend() {
        $defaults = array(
            'post_status' => 'publish',
            'post_type'   => bp_get_email_post_type(),
        );

        $emails       = bp_email_get_schema();
        $descriptions = $this->bp_email_get_unsubscribe_type_schema_extend( $emails );

        // Add these emails to the database.
        foreach ( $emails as $id => $email ) {
            if ( $id === 'bp-members-forgot-password' ) {
                // Some emails are multisite-only.
                if ( ! is_multisite() && isset( $email['args'] ) && ! empty( $email['args']['multisite'] ) ) {
                    continue;
                }

                $post_id = wp_insert_post(
                    bp_parse_args(
                        $email,
                        $defaults,
                        'install_email_' . $id
                    )
                );

                if ( ! $post_id ) {
                    continue;
                }

                $tt_ids = wp_set_object_terms( $post_id, $id, bp_get_email_tax_type() );
                foreach ( $tt_ids as $tt_id ) {
                    $term = get_term_by( 'term_taxonomy_id', (int) $tt_id, bp_get_email_tax_type() );
                    wp_update_term(
                        (int) $term->term_id,
                        bp_get_email_tax_type(),
                        array(
                            'description' => $descriptions[ $id ]['description'],
                        )
                    );
                }
            }
        }
    }

    /**
     * Set new token for email
     */
    public function bp_email_set_tokens_extend( $formatted_tokens, $tokens, $class ) {
        return $formatted_tokens;
    }

    /**
     * Extend activity item for rest response
     */
    public function bp_rest_activity_prepare_value_extend( $response, $request, $activity ) {
        $secondary_item_id = $activity->secondary_item_id;

        // set user data
        if ( $activity->user_id ) {
            $user = get_user_by( 'id', $activity->user_id );
            $response->data['user'] = array(
                'user_login'    => $user->data->user_login,
                'user_nicename' => $user->data->user_nicename,
                'display_name'  => $user->data->display_name,
            );
        } else {
            $response->data['user'] = null;
        }

        // extract post data besed on secondary item
        if ( $secondary_item_id ) {
            // post tags
            $post_tags = get_the_tags( $secondary_item_id );
            $response->data['post_tags'] = $post_tags ? $post_tags : array();

            // challenge
            $challenge_id                   = rwmb_get_value( 'challenge', array(), $secondary_item_id );
            $number_of_pages                = (int) rwmb_get_value( 'number_of_pages', array(), $challenge_id );
            $response->data['challenge']    = get_post( $challenge_id );

            // get book from challenge
            $book_id                = rwmb_get_value( 'book', array(), $challenge_id );
            $book                   = get_post( $book_id );
            if ( $book ) {
                $featured_media_url = get_the_post_thumbnail_url( $book_id, 'large' );
                $default_image      = PUSTALOKA_URL . 'public/images/placeholder_book.png';

                $book->featured_image_url = $featured_media_url ? $featured_media_url : $default_image;
                $response->data['book']   = $book;
            }

            // calculate progress in percentage
            $progress   = 0;
            $from_page  = (int) rwmb_get_value( 'from_page', array(), $secondary_item_id );
            $to_page    = (int) rwmb_get_value( 'to_page', array(), $secondary_item_id );

            if ( ! empty( $to_page ) && ! empty( $number_of_pages ) ) {
                $progress = ( $to_page / $number_of_pages ) * 100;
            }

            // reading data
            $reading_content = get_post_field( 'post_content', $secondary_item_id );
            $reading = array(
                'ID'                => $secondary_item_id,
                'from_datetime'     => rwmb_get_value( 'from_datetime', array(), $secondary_item_id ),
                'to_datetime'       => rwmb_get_value( 'to_datetime', array(), $secondary_item_id ),
                'from_page'         => $from_page,
                'to_page'           => $to_page,
                'number_of_pages'   => $number_of_pages,
                'progress'          => round( $progress, 1 ),
                'content'           => array(
                    'rendered'      => $reading_content,
                    'plain_text'    => wp_strip_all_tags( $reading_content ),
                ),
            );

            // calculate how many pages have read
            $pages_range                = range( $reading['from_page'], $reading['to_page'] );
            $reading['pages_count']     = count( $pages_range );
            $response->data['reading']  = $reading;
        } else {
            $response->data['post_tags'] = array();
        }

        // Get the count using the purpose-built recursive function.
        $counts = new \BP_Activity_Activity( $activity->id );
        $counts = $counts::get( array( 
            'count_total_only' => true,
            'display_comments' => true,
            'filter' => array(
                'action' => 'activity_comment',
                'object' => 'activity',
                'primary_id' => $activity->id,
            )
        ) );
        $response->data['comment_count'] = $counts;

        // define user owned this activity or not
        $response->data['is_owned'] = get_current_user_id() == $activity->user_id;

        // show plain content
        $response->data['content']['plain_text'] = wp_strip_all_tags( $activity->content );

        return $response;
    }

    /**
     * Extend response data member
     */
    public function bp_rest_members_prepare_value_extend( $response, $request, $user ) {
        global $wpdb;

        // populate reading data
        $counts     = array();
        $post_type  = 'challenge';
        $fields     = array(
            array( 'post_type' => 'challenge', 'meta_value' => 'ongoing' ),
            array( 'post_type' => 'challenge', 'meta_value' => 'done' ),
        );

        foreach ( $fields as $key => $value ) {
            $row = $wpdb->get_row( $wpdb->prepare( 
                "
                SELECT      COUNT(p.ID) AS total
                FROM        $wpdb->posts AS p
                LEFT JOIN   $wpdb->postmeta AS pm ON pm.post_id = p.ID
                WHERE       p.post_status = %s
                AND         p.post_type = %s
                AND         p.post_author = %d
                AND         pm.meta_key = %s
                AND         pm.meta_value = %s
                ",
                'publish', 
                $value['post_type'],
                $user->ID,
                'status',
                $value['meta_value']
            ) );

            if ( $row ) {
                $counts[$value['meta_value']] = (int) $row->total;
            } else {
                $counts[$value['meta_value']] = 0;
            }
        }

        $reading = array(
            'count' => $counts,
        );

        $response->data['reading'] = $reading;
        
        // email only show for self
        if ( get_current_user_id() == $user->ID ) {
            $response->data['email'] = $user->user_email;
        }

        // calculate friends
        $incoming = $wpdb->get_row( $wpdb->prepare( 
            "
            SELECT  COUNT(bf.id) AS total
            FROM    {$wpdb->prefix}bp_friends AS bf
            WHERE   bf.friend_user_id = %d
            AND     bf.is_confirmed = %d
            ",
            $user->ID,
            0
        ) );

        $requested = $wpdb->get_row( $wpdb->prepare( 
            "
            SELECT  COUNT(bf.id) AS total
            FROM    {$wpdb->prefix}bp_friends AS bf
            WHERE   bf.initiator_user_id = %d
            AND     bf.is_confirmed = %d
            ",
            $user->ID,
            0
        ) );

        $response->data['friendship'] = array(
            'incoming' => (int) $incoming->total,
            'requested' => (int) $requested->total,
        );

        return $response;
    }

    /**
     * Custom friends response
     */
    public function bp_rest_friends_prepare_value_extend( $response, $request, $friendship ) {
        $user_id = $request->get_param( 'user_id' );
        $friend_user_id = $friendship->friend_user_id;
        $initiator_user_id = $friendship->initiator_user_id;

        // switch initiator_id as friend_id
        if ( $user_id == $friend_user_id ) {
            $friendship->friend_user_id = $friendship->initiator_user_id;
        }

        $response->data['friend'] = array(
            'id' => $friendship->friend_user_id,
            'name' => bp_core_get_user_displayname( $friendship->friend_user_id ),
            'mention_name' => bp_core_get_username( $friendship->friend_user_id ),
            'avatar_urls' => array(
                'full'  => bp_core_fetch_avatar(
                    array(
                        'item_id' => $friendship->friend_user_id,
                        'html'    => false,
                        'type'    => 'full',
                    )
                ),
                'thumb' => bp_core_fetch_avatar(
                    array(
                        'item_id' => $friendship->friend_user_id,
                        'html'    => false,
                        'type'    => 'thumb',
                    )
                ),
            ),
        );
        
        $response->data['initiator'] = array(
            'id' => $friendship->initiator_user_id,
            'name' => bp_core_get_user_displayname( $friendship->initiator_user_id ),
            'mention_name' => bp_core_get_username( $friendship->initiator_user_id ),
            'avatar_urls' => array(
                'full'  => bp_core_fetch_avatar(
                    array(
                        'item_id' => $friendship->initiator_user_id,
                        'html'    => false,
                        'type'    => 'full',
                    )
                ),
                'thumb' => bp_core_fetch_avatar(
                    array(
                        'item_id' => $friendship->initiator_user_id,
                        'html'    => false,
                        'type'    => 'thumb',
                    )
                ),
            ),
        );

        $response->data['is_initiator'] = $user_id === $initiator_user_id;

        return $response;
    }

}