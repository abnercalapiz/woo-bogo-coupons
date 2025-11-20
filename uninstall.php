<?php
/**
 * Uninstall Woo BOGO Coupons
 *
 * @package WooCommerce_BOGO_Coupons
 */

// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbc_bogo_rules" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbc_bogo_usage" );

// Delete options
delete_option( 'wbc_enable_auto_add' );
delete_option( 'wbc_show_free_price' );
delete_option( 'wbc_free_product_text' );
delete_option( 'wbc_remove_free_on_coupon_removal' );
delete_option( 'wbc_allow_free_quantity_change' );
delete_option( 'wbc_version' );

// Remove capabilities
global $wp_roles;

if ( class_exists( 'WP_Roles' ) ) {
    if ( ! isset( $wp_roles ) ) {
        $wp_roles = new WP_Roles();
    }

    $capabilities = array(
        'manage_wbc_bogo_coupons',
        'view_wbc_bogo_reports',
    );

    foreach ( $capabilities as $cap ) {
        $wp_roles->remove_cap( 'shop_manager', $cap );
        $wp_roles->remove_cap( 'administrator', $cap );
    }
}

// Clear any cached data
wp_cache_flush();