<?php
/**
 * Plugin Name: Woo BOGO Coupons
 * Plugin URI: https://www.jezweb.com.au/
 * Description: Create Buy One Get One (BOGO) coupons for WooCommerce. Automatically add free products to cart when customers purchase qualifying items.
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Jezweb
 * Author URI: https://www.jezweb.com.au/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-bogo-coupons
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 8.3
 * 
 * @package WooCommerce_BOGO_Coupons
 * @author Jezweb
 * @copyright 2024 Jezweb
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WBC_VERSION', '1.0.1' );
define( 'WBC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class Woo_BOGO_Coupons {

    /**
     * Single instance of the class
     *
     * @var Woo_BOGO_Coupons
     */
    private static $instance = null;

    /**
     * Main plugin instance
     *
     * @return Woo_BOGO_Coupons
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        if ( ! $this->check_requirements() ) {
            return;
        }
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return false;
        }
        return true;
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Woo BOGO Coupons requires WooCommerce to be installed and active.', 'woo-bogo-coupons' ); ?></p>
        </div>
        <?php
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core files
        require_once WBC_PLUGIN_DIR . 'includes/class-wbc-install.php';
        require_once WBC_PLUGIN_DIR . 'includes/class-wbc-debug.php';
        require_once WBC_PLUGIN_DIR . 'includes/class-wbc-coupon-handler.php';
        require_once WBC_PLUGIN_DIR . 'includes/class-wbc-cart-handler.php';
        
        // Admin files
        if ( is_admin() ) {
            require_once WBC_PLUGIN_DIR . 'admin/class-wbc-admin.php';
            require_once WBC_PLUGIN_DIR . 'admin/class-wbc-admin-coupons.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Installation
        register_activation_hook( __FILE__, array( 'WBC_Install', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'WBC_Install', 'deactivate' ) );
        
        // Initialize classes
        add_action( 'init', array( $this, 'init' ), 0 );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        
        // Plugin action links
        add_filter( 'plugin_action_links_' . WBC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize handlers
        new WBC_Coupon_Handler();
        new WBC_Cart_Handler();
        
        if ( is_admin() ) {
            new WBC_Admin();
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'woo-bogo-coupons', false, dirname( WBC_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Add plugin action links
     */
    public function plugin_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=wbc_settings' ) . '">' . __( 'Settings', 'woo-bogo-coupons' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }
}

/**
 * Returns the main instance of Woo_BOGO_Coupons
 *
 * @return Woo_BOGO_Coupons
 */
function WBC() {
    return Woo_BOGO_Coupons::instance();
}

// Initialize the plugin
WBC();