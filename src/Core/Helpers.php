<?php

namespace Pointilis\Pustaloka\Core;

use OTPHP\TOTP;
use PHLAK\StrGen;

class Helpers {

    public static function generate_activation_key() {
        $totp = TOTP::generate();
        $totp->setDigest('sha512'); 
        $totp->setDigits(4);
    
        $generator = new StrGen\Generator();
        $generator->length(2)->charset(StrGen\CharSet::UPPER_ALPHA);
    
        $key = $totp->now() . $generator->generate();
        
        return $key;
    }

    public function write_log( $log ) {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }

    /**
     * Check if this is a request at the backend.
     *
     * @return bool true if is admin request, otherwise false.
     */
    public static function is_admin_request() {
        /**
         * Get current URL.
         *
         * @link https://wordpress.stackexchange.com/a/126534
         */
        $current_url = home_url( add_query_arg( null, null ) );

        /**
         * Get admin URL and referrer.
         *
         * @link https://core.trac.wordpress.org/browser/tags/4.8/src/wp-includes/pluggable.php#L1076
         */
        $admin_url = strtolower( admin_url() );
        $referrer  = strtolower( wp_get_referer() );

        /**
         * Check if this is a admin request. If true, it
         * could also be a AJAX request from the frontend.
         */
        if ( 0 === strpos( $current_url, $admin_url ) ) {
            /**
             * Check if the user comes from a admin page.
             */
            if ( 0 === strpos( $referrer, $admin_url ) ) {
                return true;
            } else {
                /**
                 * Check for AJAX requests.
                 *
                 * @link https://gist.github.com/zitrusblau/58124d4b2c56d06b070573a99f33b9ed#file-lazy-load-responsive-images-php-L193
                 */
                if ( function_exists( 'wp_doing_ajax' ) ) {
                    return ! wp_doing_ajax();
                } else {
                    return ! ( defined( 'DOING_AJAX' ) && DOING_AJAX );
                }
            }
        } else {
            return false;
        }
    }

}