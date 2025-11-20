<?php
/**
 * Debug helper for BOGO functionality
 *
 * @package WooCommerce_BOGO_Coupons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WBC_Debug Class
 */
class WBC_Debug {

    /**
     * Log debug information
     */
    public static function log( $message, $context = array() ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        $log_entry = '[BOGO Debug] ' . $message;
        
        if ( ! empty( $context ) ) {
            $log_entry .= ' | Context: ' . wp_json_encode( $context );
        }
        
        error_log( $log_entry );
        
        // Also add as WooCommerce log if available
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->debug( $message, array( 'source' => 'woo-bogo-coupons' ) );
        }
    }
    
    /**
     * Add debug notice
     */
    public static function add_notice( $message, $type = 'notice' ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( '[BOGO Debug] ' . $message, $type );
        }
    }
}