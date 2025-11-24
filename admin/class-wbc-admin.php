<?php
/**
 * Admin functionality
 *
 * @package WooCommerce_BOGO_Coupons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WBC_Admin Class
 */
class WBC_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
        add_action( 'woocommerce_settings_tabs_wbc_settings', array( $this, 'settings_tab' ) );
        add_action( 'woocommerce_update_options_wbc_settings', array( $this, 'update_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_menu_items' ) );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts( $hook ) {
        global $post;

        // Only load on coupon pages
        if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && 
             ( ( $post && $post->post_type === 'shop_coupon' ) || ( isset( $_GET['post_type'] ) && sanitize_text_field( $_GET['post_type'] ) === 'shop_coupon' ) ) ) {
            
            // Enqueue WooCommerce admin scripts
            wp_enqueue_script( 'wc-enhanced-select' );
            wp_enqueue_script( 'selectWoo' );
            wp_enqueue_style( 'woocommerce_admin_styles' );
            
            wp_enqueue_script( 
                'wbc-admin', 
                WBC_PLUGIN_URL . 'assets/js/admin-fixed.js', 
                array( 'jquery', 'selectWoo', 'wc-enhanced-select' ), 
                WBC_VERSION, 
                true 
            );

            wp_enqueue_style( 
                'wbc-admin', 
                WBC_PLUGIN_URL . 'assets/css/admin.css', 
                array( 'woocommerce_admin_styles' ), 
                WBC_VERSION 
            );

            wp_localize_script( 'wbc-admin', 'wbc_admin', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wbc-admin-nonce' ),
                'search_nonce' => wp_create_nonce( 'search-products' ),
                'i18n' => array(
                    'confirm_remove' => __( 'Are you sure you want to remove this rule?', 'woo-bogo-coupons' ),
                    'search_product' => __( 'Search for a product…', 'woo-bogo-coupons' ),
                    'unlimited' => __( 'Unlimited', 'woo-bogo-coupons' ),
                    'remove' => __( 'Remove', 'woo-bogo-coupons' ),
                ),
            ) );
        }
    }

    /**
     * Add settings tab
     */
    public function add_settings_tab( $tabs ) {
        $tabs['wbc_settings'] = __( 'BOGO Coupons', 'woo-bogo-coupons' );
        return $tabs;
    }

    /**
     * Settings tab content
     */
    public function settings_tab() {
        woocommerce_admin_fields( $this->get_settings() );
    }

    /**
     * Update settings
     */
    public function update_settings() {
        woocommerce_update_options( $this->get_settings() );
    }

    /**
     * Get settings array
     */
    public function get_settings() {
        $settings = array(
            array(
                'title' => __( 'BOGO Coupon Settings', 'woo-bogo-coupons' ),
                'type' => 'title',
                'desc' => __( 'Configure how BOGO coupons work in your store.', 'woo-bogo-coupons' ),
                'id' => 'wbc_settings_section',
            ),
            array(
                'title' => __( 'Auto-add Free Products', 'woo-bogo-coupons' ),
                'desc' => __( 'Automatically add free products to cart when qualifying products are added', 'woo-bogo-coupons' ),
                'id' => 'wbc_enable_auto_add',
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'title' => __( 'Auto-apply BOGO Coupons', 'woo-bogo-coupons' ),
                'desc' => __( 'Automatically apply BOGO coupons when qualifying products are in cart', 'woo-bogo-coupons' ),
                'id' => 'wbc_auto_apply_coupons',
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'title' => __( 'Show Free Price', 'woo-bogo-coupons' ),
                'desc' => __( 'Display "FREE" text for 100% discount items', 'woo-bogo-coupons' ),
                'id' => 'wbc_show_free_price',
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'title' => __( 'Free Product Text', 'woo-bogo-coupons' ),
                'desc' => __( 'Text to display for free products', 'woo-bogo-coupons' ),
                'id' => 'wbc_free_product_text',
                'type' => 'text',
                'default' => __( 'FREE - BOGO Offer', 'woo-bogo-coupons' ),
                'css' => 'width:300px;',
            ),
            array(
                'title' => __( 'Remove Free Items on Coupon Removal', 'woo-bogo-coupons' ),
                'desc' => __( 'Automatically remove free products when BOGO coupon is removed', 'woo-bogo-coupons' ),
                'id' => 'wbc_remove_free_on_coupon_removal',
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'title' => __( 'Allow Quantity Changes', 'woo-bogo-coupons' ),
                'desc' => __( 'Allow customers to change quantity of free items', 'woo-bogo-coupons' ),
                'id' => 'wbc_allow_free_quantity_change',
                'type' => 'checkbox',
                'default' => 'no',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wbc_settings_section',
            ),
        );

        return apply_filters( 'wbc_settings', $settings );
    }

    /**
     * Add menu items
     */
    public function add_menu_items() {
        add_submenu_page(
            'woocommerce',
            __( 'BOGO Coupon Reports', 'woo-bogo-coupons' ),
            __( 'BOGO Reports', 'woo-bogo-coupons' ),
            'view_wbc_bogo_reports',
            'wbc-reports',
            array( $this, 'reports_page' )
        );
    }

    /**
     * Reports page
     */
    public function reports_page() {
        include_once WBC_PLUGIN_DIR . 'admin/views/reports.php';
    }
}