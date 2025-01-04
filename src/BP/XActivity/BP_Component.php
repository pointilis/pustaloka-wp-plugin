<?php

namespace Pointilis\Pustaloka\BP\XActivity;

// use Pointilis\Pustaloka\BP\XActivity\BP_REST_Signup_Endpoint;
// use Pointilis\Pustaloka\BP\XActivity\BP_REST_Members_Endpoint;

#[\AllowDynamicProperties]
class BP_Component extends \BP_Component {

    public function __construct() {
        parent::start(
            'xactivity',
            __( 'XActivity', 'pustaloka' ),
            plugin_dir_path( dirname( __FILE__ ) ),
        );

        // Make sure the `buddypress()->active_components` global lists all active components.
		if ( 'core' !== $this->id && ! isset( buddypress()->active_components[ $this->id ] ) ) {
			buddypress()->active_components[ $this->id ] = '1';
		}
    }

    public function setup_actions() {
		parent::setup_actions();

		// Register BP REST Endpoints.
		if ( bp_rest_api_is_available() ) {
            if ( ! bp_rest_in_buddypress() ) {
                remove_action( 'bp_rest_api_init', 'bp_rest', 5 );
            }

            add_action( 'bp_rest_api_init', array( $this, 'rest_api_init' ), 9 );
		}
	}

    public function rest_api_init( $controllers = array() ) {
		// $controller = new BP_REST_Signup_Endpoint();
        // $controller->register_routes();

        // $controller = new BP_REST_Members_Endpoint();
        // $controller->register_routes();

        parent::rest_api_init( $controllers );
	}

}