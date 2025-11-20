<?php
/**
 * Handle BOGO coupon functionality
 *
 * @package WooCommerce_BOGO_Coupons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WBC_Coupon_Handler Class
 */
class WBC_Coupon_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        // Add custom coupon type
        add_filter( 'woocommerce_coupon_discount_types', array( $this, 'add_bogo_coupon_type' ) );
        
        // Validate BOGO coupons
        add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_bogo_coupon' ), 10, 3 );
        
        // Handle coupon meta fields
        add_action( 'woocommerce_coupon_options', array( $this, 'add_coupon_meta_fields' ), 25, 2 );
        add_action( 'woocommerce_coupon_options_save', array( $this, 'save_coupon_meta_fields' ), 10, 2 );
    }

    /**
     * Add BOGO coupon type
     */
    public function add_bogo_coupon_type( $types ) {
        $types['bogo_coupon'] = __( 'BOGO (Buy One Get One)', 'woo-bogo-coupons' );
        return $types;
    }

    /**
     * Validate BOGO coupon
     */
    public function validate_bogo_coupon( $valid, $coupon, $discount ) {
        if ( ! $valid || $coupon->get_discount_type() !== 'bogo_coupon' ) {
            return $valid;
        }

        // Get BOGO rules for this coupon
        $rules = $this->get_bogo_rules( $coupon->get_id() );
        
        if ( empty( $rules ) ) {
            return false;
        }

        // Check if cart contains qualifying products
        $cart = WC()->cart;
        $cart_items = $cart->get_cart();
        
        foreach ( $rules as $rule ) {
            $buy_product_qty = 0;
            
            foreach ( $cart_items as $item ) {
                if ( $item['product_id'] == $rule->buy_product_id || 
                     ( $item['variation_id'] && $item['variation_id'] == $rule->buy_product_id ) ) {
                    $buy_product_qty += $item['quantity'];
                }
            }
            
            if ( $buy_product_qty >= $rule->buy_quantity ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add coupon meta fields
     */
    public function add_coupon_meta_fields( $coupon_id, $coupon ) {
        ?>
        <div class="bogo-coupon-fields" style="display: none;">
            <h3><?php esc_html_e( 'BOGO Settings', 'woo-bogo-coupons' ); ?></h3>
            
            <p class="form-field">
                <label for="wbc_auto_apply"><?php esc_html_e( 'Auto-apply this coupon', 'woo-bogo-coupons' ); ?></label>
                <?php
                $auto_apply = get_post_meta( $coupon_id, 'wbc_auto_apply', true );
                if ( $auto_apply === '' ) {
                    $auto_apply = 'yes'; // Default value
                }
                ?>
                <input type="checkbox" name="wbc_auto_apply" id="wbc_auto_apply" value="yes" <?php checked( $auto_apply, 'yes' ); ?> />
                <span class="description"><?php esc_html_e( 'Automatically apply this coupon when qualifying products are in the cart', 'woo-bogo-coupons' ); ?></span>
            </p>
            
            
            <div id="bogo-rules-container">
                <table class="bogo-rules-table widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Buy Product', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Buy Qty', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Get Product', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Get Qty', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Discount %', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Max Free Qty', 'woo-bogo-coupons' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="bogo-rules-list">
                        <?php
                        $rules = $this->get_bogo_rules( $coupon_id );
                        if ( ! empty( $rules ) ) {
                            foreach ( $rules as $index => $rule ) {
                                $this->render_rule_row( $index, $rule );
                            }
                        } else {
                            $this->render_rule_row( 0 );
                        }
                        ?>
                    </tbody>
                </table>
                
                <button type="button" class="button" id="add-bogo-rule">
                    <?php esc_html_e( 'Add Rule', 'woo-bogo-coupons' ); ?>
                </button>
            </div>
            
            <!-- BOGO Rule Template for JavaScript -->
            <div id="bogo-rule-template" style="display: none;">
                <?php $this->render_rule_row( '{{index}}' ); ?>
            </div>
            
            <div style="margin-top: 20px;">
                <p class="description">
                    <strong><?php esc_html_e( 'Note:', 'woo-bogo-coupons' ); ?></strong> 
                    <?php esc_html_e( 'For variable products:', 'woo-bogo-coupons' ); ?>
                    <br>• <?php esc_html_e( 'Select the parent product to match ANY variation', 'woo-bogo-coupons' ); ?>
                    <br>• <?php esc_html_e( 'Select a specific variation to match ONLY that variation', 'woo-bogo-coupons' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render rule row
     */
    private function render_rule_row( $index, $rule = null ) {
        $buy_product_id = $rule ? $rule->buy_product_id : '';
        $buy_quantity = $rule ? $rule->buy_quantity : 1;
        $get_product_id = $rule ? $rule->get_product_id : '';
        $get_quantity = $rule ? $rule->get_quantity : 1;
        $discount = $rule ? $rule->get_discount_percentage : 100;
        $max_free = $rule ? $rule->max_free_quantity : '';
        ?>
        <tr class="bogo-rule-row">
            <td>
                <select name="bogo_rules[<?php echo esc_attr( $index ); ?>][buy_product_id]" 
                        class="wc-product-search" 
                        style="width: 100%;" 
                        data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woo-bogo-coupons' ); ?>"
                        data-action="woocommerce_json_search_products_and_variations">
                    <?php
                    if ( $buy_product_id ) {
                        $product = wc_get_product( $buy_product_id );
                        if ( $product ) {
                            echo '<option value="' . esc_attr( $buy_product_id ) . '" selected="selected">' . 
                                 esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
                        } else {
                            // If product not found, still show the ID
                            echo '<option value="' . esc_attr( $buy_product_id ) . '" selected="selected">Product #' . esc_html( $buy_product_id ) . '</option>';
                        }
                    }
                    ?>
                </select>
            </td>
            <td>
                <input type="number" 
                       name="bogo_rules[<?php echo esc_attr( $index ); ?>][buy_quantity]" 
                       value="<?php echo esc_attr( $buy_quantity ); ?>" 
                       min="1" 
                       style="width: 60px;">
            </td>
            <td>
                <select name="bogo_rules[<?php echo esc_attr( $index ); ?>][get_product_id]" 
                        class="wc-product-search" 
                        style="width: 100%;" 
                        data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woo-bogo-coupons' ); ?>"
                        data-action="woocommerce_json_search_products_and_variations">
                    <?php
                    if ( $get_product_id ) {
                        $product = wc_get_product( $get_product_id );
                        if ( $product ) {
                            echo '<option value="' . esc_attr( $get_product_id ) . '" selected="selected">' . 
                                 esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
                        }
                    }
                    ?>
                </select>
            </td>
            <td>
                <input type="number" 
                       name="bogo_rules[<?php echo esc_attr( $index ); ?>][get_quantity]" 
                       value="<?php echo esc_attr( $get_quantity ); ?>" 
                       min="1" 
                       style="width: 60px;">
            </td>
            <td>
                <input type="number" 
                       name="bogo_rules[<?php echo esc_attr( $index ); ?>][discount]" 
                       value="<?php echo esc_attr( $discount ); ?>" 
                       min="0" 
                       max="100" 
                       step="0.01"
                       style="width: 80px;">
            </td>
            <td>
                <input type="number" 
                       name="bogo_rules[<?php echo esc_attr( $index ); ?>][max_free_quantity]" 
                       value="<?php echo esc_attr( $max_free ); ?>" 
                       min="0" 
                       placeholder="<?php esc_attr_e( 'Unlimited', 'woo-bogo-coupons' ); ?>"
                       style="width: 80px;">
            </td>
            <td>
                <button type="button" class="button remove-bogo-rule">
                    <?php esc_html_e( 'Remove', 'woo-bogo-coupons' ); ?>
                </button>
            </td>
        </tr>
        <?php
    }

    /**
     * Save coupon meta fields
     */
    public function save_coupon_meta_fields( $coupon_id, $coupon ) {
        // Check if user can edit posts
        if ( ! current_user_can( 'edit_shop_coupons' ) ) {
            return;
        }

        if ( ! isset( $_POST['discount_type'] ) || $_POST['discount_type'] !== 'bogo_coupon' ) {
            // Delete auto-apply setting if not a BOGO coupon
            delete_post_meta( $coupon_id, 'wbc_auto_apply' );
            return;
        }

        // Save auto-apply setting
        $auto_apply = isset( $_POST['wbc_auto_apply'] ) && $_POST['wbc_auto_apply'] === 'yes' ? 'yes' : 'no';
        update_post_meta( $coupon_id, 'wbc_auto_apply', $auto_apply );

        global $wpdb;

        // Delete existing rules
        $wpdb->delete( $wpdb->prefix . 'wbc_bogo_rules', array( 'coupon_id' => $coupon_id ) );

        // Save new rules
        if ( isset( $_POST['bogo_rules'] ) && is_array( $_POST['bogo_rules'] ) ) {
            foreach ( $_POST['bogo_rules'] as $rule ) {
                if ( empty( $rule['buy_product_id'] ) || empty( $rule['get_product_id'] ) ) {
                    continue;
                }

                $wpdb->insert(
                    $wpdb->prefix . 'wbc_bogo_rules',
                    array(
                        'coupon_id' => $coupon_id,
                        'buy_product_id' => absint( $rule['buy_product_id'] ),
                        'buy_quantity' => absint( $rule['buy_quantity'] ),
                        'get_product_id' => absint( $rule['get_product_id'] ),
                        'get_quantity' => absint( $rule['get_quantity'] ),
                        'get_discount_percentage' => floatval( $rule['discount'] ),
                        'max_free_quantity' => ! empty( $rule['max_free_quantity'] ) ? absint( $rule['max_free_quantity'] ) : null,
                    ),
                    array(
                        '%d', '%d', '%d', '%d', '%d', '%f', '%d'
                    )
                );
            }
        }
    }

    /**
     * Get BOGO rules for a coupon
     */
    public function get_bogo_rules( $coupon_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wbc_bogo_rules WHERE coupon_id = %d",
            $coupon_id
        ) );
    }

}