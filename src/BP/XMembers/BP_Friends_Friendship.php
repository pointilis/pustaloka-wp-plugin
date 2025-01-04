<?php

namespace Pointilis\Pustaloka\BP\XMembers;

use Pointilis\Pustaloka\Core\Helpers;

#[AllowDynamicProperties]
class BP_Friends_Friendship extends \BP_Friends_Friendship {

    /** Static Methods ********************************************************/

	/**
	 * Get the friendships for a given user.
	 *
	 * @since 2.6.0
	 *
	 * @param int    $user_id  ID of the user whose friends are being retrieved.
	 * @param array  $args     {
	 *        Optional. Filter parameters.
	 *        @type int    $id                ID of specific friendship to retrieve.
	 *        @type int    $initiator_user_id ID of friendship initiator.
	 *        @type int    $friend_user_id    ID of specific friendship to retrieve.
	 *        @type int    $is_confirmed      Whether the friendship has been accepted.
	 *        @type int    $is_limited        Whether the friendship is limited.
	 *        @type string $order_by          Column name to order by.
	 *        @type string $sort_order        Optional. ASC or DESC. Default: 'DESC'.
	 * }
	 * @param string $operator Optional. Operator to use in `wp_list_filter()`.
	 *
	 * @return array $friendships Array of friendship objects.
	 */
	public static function get_friendships( $user_id, $args = array(), $operator = 'AND' ) {

		if ( empty( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		$friendships = array();
		$operator    = strtoupper( $operator );

		if ( ! in_array( $operator, array( 'AND', 'OR', 'NOT' ), true ) ) {
			return $friendships;
		}

		$r = bp_parse_args(
			$args,
			array(
				'id'                => null,
				'initiator_user_id' => null,
				'friend_user_id'    => null,
				'is_confirmed'      => null,
				'is_limited'        => null,
				'order_by'          => 'date_created',
				'sort_order'        => 'DESC',
				'page'              => null,
				'per_page'          => null,
			),
			'bp_get_user_friendships'
		);

		// First, we get all friendships that involve the user.
		$friendship_ids = wp_cache_get( $user_id, 'bp_friends_friendships_for_user' );
		if ( false === $friendship_ids ) {
			$friendship_ids = self::get_friendship_ids_for_user_extend( $user_id, $args );
			wp_cache_set( $user_id, $friendship_ids, 'bp_friends_friendships_for_user' );
		}

		// Prime the membership cache.
		$uncached_friendship_ids = bp_get_non_cached_ids( $friendship_ids, 'bp_friends_friendships' );
		if ( ! empty( $uncached_friendship_ids ) ) {
			$uncached_friendships = self::get_friendships_by_id( $uncached_friendship_ids );

			foreach ( $uncached_friendships as $uncached_friendship ) {
				wp_cache_set( $uncached_friendship->id, $uncached_friendship, 'bp_friends_friendships' );
			}
		}

		$int_keys  = array( 'id', 'initiator_user_id', 'friend_user_id' );
		$bool_keys = array( 'is_confirmed', 'is_limited' );

		// Assemble filter array.
		$filters = wp_array_slice_assoc( $r, array( 'id', 'initiator_user_id', 'friend_user_id', 'is_confirmed', 'is_limited' ) );
		foreach ( $filters as $filter_name => $filter_value ) {
			if ( is_null( $filter_value ) ) {
				unset( $filters[ $filter_name ] );
			} elseif ( in_array( $filter_name, $int_keys, true ) ) {
				$filters[ $filter_name ] = (int) $filter_value;
			} else {
				$filters[ $filter_name ] = (bool) $filter_value;
			}
		}

		// Populate friendship array from cache, and normalize.
		foreach ( $friendship_ids as $friendship_id ) {
			// Create a limited BP_Friends_Friendship object (don't fetch the user details).
			$friendship = new BP_Friends_Friendship( $friendship_id, false, false );

			// Sanity check.
			if ( ! isset( $friendship->id ) ) {
				continue;
			}

			// Integer values.
			foreach ( $int_keys as $index ) {
				$friendship->{$index} = intval( $friendship->{$index} );
			}

			// Boolean values.
			foreach ( $bool_keys as $index ) {
				$friendship->{$index} = (bool) $friendship->{$index};
			}

			// We need to support the same operators as wp_list_filter().
			if ( 'OR' === $operator || 'NOT' === $operator ) {
				$matched = 0;

				foreach ( $filters as $filter_name => $filter_value ) {
					if ( isset( $friendship->{$filter_name} ) && $filter_value === $friendship->{$filter_name} ) {
						$matched++;
					}
				}

				if ( ( 'OR' === $operator && $matched > 0 )
				  || ( 'NOT' === $operator && 0 === $matched ) ) {
					$friendships[ $friendship->id ] = $friendship;
				}

			} else {
				/*
				 * This is the more typical 'AND' style of filter.
				 * If any of the filters miss, we move on.
				 */
				foreach ( $filters as $filter_name => $filter_value ) {
					if ( ! isset( $friendship->{$filter_name} ) || $filter_value !== $friendship->{$filter_name} ) {
						continue 2;
					}
				}
				$friendships[ $friendship->id ] = $friendship;
			}

		}

		// Sort the results on a column name.
		if ( in_array( $r['order_by'], array( 'id', 'initiator_user_id', 'friend_user_id' ) ) ) {
			$friendships = bp_sort_by_key( $friendships, $r['order_by'], 'num', true );
		}

		// Adjust the sort direction of the results.
		if ( 'ASC' === bp_esc_sql_order( $r['sort_order'] ) ) {
			// `true` to preserve keys.
			$friendships = array_reverse( $friendships, true );
		}

		// Paginate the results.
		if ( $r['per_page'] && $r['page'] ) {
			$start       = ( $r['page'] - 1 ) * ( $r['per_page'] );
			$friendships = array_slice( $friendships, $start, $r['per_page'] );
		}

		return $friendships;
	}

    /**
	 * Get all friendship IDs for a user.
	 *
	 * @since 2.7.0
	 *
	 * @global wpdb $wpdb WordPress database object.
	 *
	 * @param int $user_id ID of the user.
	 * @return array
	 */
	public static function get_friendship_ids_for_user_extend( $user_id, $args ) {
		global $wpdb;

        $show_as        = $args['show_as'];
        $user_id        = get_current_user_id();
		$bp             = buddypress();
        $friendship_ids = array();

        if ( $show_as === 'friend' ) {
            $friendship_ids = $wpdb->get_col( $wpdb->prepare( 
                "
                SELECT      id 
                FROM        {$bp->friends->table_name} 
                WHERE       (initiator_user_id = %d OR friend_user_id = %d)
                ORDER BY    date_created DESC
                ", 
                $user_id, 
                $user_id
            ) );
        } else if ( $show_as === 'requested' ) {
            // Me send friend request to a user
            $friendship_ids = $wpdb->get_col( $wpdb->prepare( 
                "
                SELECT      id 
                FROM        {$bp->friends->table_name} 
                WHERE       initiator_user_id = %d
                AND         is_confirmed = %d
                ORDER BY    date_created DESC
                ", 
                $user_id,
                0
            ) );
        } else if ( $show_as === 'incoming' ) {
            // A user send friend request to me
            $friendship_ids = $wpdb->get_col( $wpdb->prepare( 
                "
                SELECT      id 
                FROM        {$bp->friends->table_name} 
                WHERE       friend_user_id = %d
                AND         is_confirmed = %d
                ORDER BY    date_created DESC
                ", 
                $user_id,
                0
            ) );
        }

		return $friendship_ids;
	}

}