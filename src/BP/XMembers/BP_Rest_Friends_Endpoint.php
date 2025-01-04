<?php

namespace Pointilis\Pustaloka\BP\XMembers;

use Pointilis\Pustaloka\Core\Helpers;
use Pointilis\Pustaloka\BP\XMembers\BP_Friends_Friendship;

class BP_REST_Friends_Endpoint extends \BP_REST_Friends_Endpoint {

    /**
	 * Retrieve friendships.
	 *
	 * @since 15.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$args = array(
			'id'                => $request->get_param( 'id' ),
			'initiator_user_id' => $request->get_param( 'initiator_id' ),
			'friend_user_id'    => $request->get_param( 'friend_id' ),
			'is_confirmed'      => $request->get_param( 'is_confirmed' ),
			'order_by'          => $request->get_param( 'order_by' ),
			'sort_order'        => strtoupper( $request->get_param( 'order' ) ),
			'page'              => $request->get_param( 'page' ),
			'per_page'          => $request->get_param( 'per_page' ),
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 15.0.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_friends_get_items_query_args', $args, $request );

		// null is the default values.
		foreach ( $args as $key => $value ) {
			if ( empty( $value ) ) {
				$args[ $key ] = null;
			}
		}

		// Check if user is valid.
		$user = get_user_by( 'id', $request->get_param( 'user_id' ) );
		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error(
				'bp_rest_friends_get_items_user_failed',
				__( 'There was a problem confirming if user is valid.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Actually, query it.
		$friendships = BP_Friends_Friendship::get_friendships( $user->ID, $args );

		$retval = array();
		foreach ( $friendships as $friendship ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $friendship, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, count( $friendships ), $args['per_page'] );

		/**
		 * Fires after friendships are fetched via the REST API.
		 *
		 * @since 15.0.0
		 *
		 * @param array            $friendships Fetched friendships.
		 * @param WP_REST_Response $response    The response data.
		 * @param WP_REST_Request  $request     The request sent to the API.
		 */
		do_action( 'bp_rest_friends_get_items', $friendships, $response, $request );

		return $response;
	}

}