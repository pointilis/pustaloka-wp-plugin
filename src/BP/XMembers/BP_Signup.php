<?php

namespace Pointilis\Pustaloka\BP\XMembers;

use Pointilis\Pustaloka\Core\Helpers;

#[\AllowDynamicProperties]
class BP_Signup extends \BP_Signup {

    /**
     * Override add method
     */
    public static function add( $args = array() ) {
		global $wpdb;

		$r = bp_parse_args(
			$args,
			array(
				'domain'         => '',
				'path'           => '',
				'title'          => '',
				'user_login'     => '',
				'user_email'     => '',
				'registered'     => current_time( 'mysql', true ),
				'activation_key' => wp_generate_password( 32, false ),
				'meta'           => array(),
			),
			'bp_core_signups_add_args'
		);

		// Ensure that sent_date and count_sent are set in meta.
		if ( ! isset( $r['meta']['sent_date'] ) ) {
			$r['meta']['sent_date'] = '0000-00-00 00:00:00';
		}
		if ( ! isset( $r['meta']['count_sent'] ) ) {
			$r['meta']['count_sent'] = 0;
		}

		$r['meta'] = maybe_serialize( $r['meta'] );

        // Check email exists but not active yet
        $signup = null;
        $all_signup = self::get( array(
            'user_email'    => $r['user_email'],
            'active'        => 0,
        ) );

        if ( ! empty( $all_signup['signups'] ) ) {
			$signup = reset( $all_signup['signups'] );
		}

        if ( empty( $signup ) && $all_signup['total'] == 0 ) {
            // Insert new user
            $inserted = $wpdb->insert(
                buddypress()->members->table_name_signups,
                $r,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            if ( $inserted ) {
                $retval = $wpdb->insert_id;
            } else {
                $retval = false;
            }
        } else {
            // Update activation key only
            $activation_key = Helpers::generate_activation_key();
            $updated = $wpdb->update(
                buddypress()->members->table_name_signups,
                array(
                    'activation_key'    => $activation_key,
                ),
                array(
                    'signup_id'     => $signup->id,
                    'user_email'    => $signup->user_email,
                    'active'        => 0,
                )
            );

            if ( is_wp_error( $updated ) ) {
                $retval = false;
            } else {
                $retval = $signup->id;
            }
        }

		/**
		 * Fires after adding a new BP_Signup.
		 *
		 * @since 10.0.0
		 *
		 * @param int|bool $retval ID of the BP_Signup just added.
		 * @param array    $r      Array of parsed arguments for add() method.
		 * @param array    $args   Array of original arguments for add() method.
		 */
		do_action( 'bp_core_signups_after_add', $retval, $r, $args );

		/**
		 * Filters the result of a signup addition.
		 *
		 * @since 2.0.0
		 *
		 * @param int|bool $retval Newly added signup ID on success, false on failure.
		 */
		return apply_filters( 'bp_core_signups_add', $retval );
	}
    
}