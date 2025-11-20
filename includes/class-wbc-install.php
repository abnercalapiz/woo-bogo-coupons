<?php
/**
 * Installation related functions and actions
 *
 * @package WooCommerce_BOGO_Coupons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WBC_Install Class
 */
class WBC_Install {

    /**
     * Hook in tabs
     */
    public static function init() {
        add_filter( 'wpmu_drop_tables', array( __CLASS__, 'wpmu_drop_tables' ) );
    }

    /**
     * Install WBC
     */
    public static function activate() {
        if ( ! is_blog_installed() ) {
            return;
        }

        // Check if we are not already running this routine
        if ( 'yes' === get_transient( 'wbc_installing' ) ) {
            return;
        }

        // If we made it till here nothing is running yet, let's set the transient now
        set_transient( 'wbc_installing', 'yes', MINUTE_IN_SECONDS * 10 );

        self::create_tables();
        self::create_options();
        self::create_roles();
        
        delete_transient( 'wbc_installing' );

        // Trigger action
        do_action( 'wbc_installed' );
    }

    /**
     * Deactivate
     */
    public static function deactivate() {
        // Clear any scheduled hooks
        wp_clear_scheduled_hook( 'wbc_cleanup_sessions' );
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = self::get_schema();

        foreach ( $tables as $table ) {
            dbDelta( $table );
        }
    }

    /**
     * Get table schema
     */
    private static function get_schema() {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        $tables = array();

        $tables[] = "CREATE TABLE {$wpdb->prefix}wbc_bogo_rules (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) UNSIGNED NOT NULL,
            buy_product_id bigint(20) UNSIGNED NOT NULL,
            buy_quantity int(11) NOT NULL DEFAULT 1,
            get_product_id bigint(20) UNSIGNED NOT NULL,
            get_quantity int(11) NOT NULL DEFAULT 1,
            get_discount_percentage decimal(5,2) NOT NULL DEFAULT 100.00,
            max_free_quantity int(11) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY buy_product_id (buy_product_id),
            KEY get_product_id (get_product_id)
        ) $collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}wbc_bogo_usage (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NULL,
            free_quantity int(11) NOT NULL,
            used_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rule_id (rule_id),
            KEY order_id (order_id),
            KEY user_id (user_id)
        ) $collate;";

        return $tables;
    }

    /**
     * Create default options
     */
    private static function create_options() {
        $default_options = array(
            'wbc_enable_auto_add' => 'yes',
            'wbc_auto_apply_coupons' => 'yes',
            'wbc_show_free_price' => 'yes',
            'wbc_free_product_text' => __( 'FREE - BOGO Offer', 'woo-bogo-coupons' ),
            'wbc_version' => WBC_VERSION,
        );

        foreach ( $default_options as $key => $value ) {
            add_option( $key, $value );
        }
    }

    /**
     * Create roles and capabilities
     */
    private static function create_roles() {
        global $wp_roles;

        if ( ! class_exists( 'WP_Roles' ) ) {
            return;
        }

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        // Add capabilities to admin and shop manager
        $capabilities = array(
            'manage_wbc_bogo_coupons',
            'view_wbc_bogo_reports',
        );

        foreach ( $capabilities as $cap ) {
            $wp_roles->add_cap( 'shop_manager', $cap );
            $wp_roles->add_cap( 'administrator', $cap );
        }
    }

    /**
     * Tables to drop on blog deletion
     */
    public static function wpmu_drop_tables( $tables ) {
        global $wpdb;

        $tables[] = $wpdb->prefix . 'wbc_bogo_rules';
        $tables[] = $wpdb->prefix . 'wbc_bogo_usage';

        return $tables;
    }
}

WBC_Install::init();