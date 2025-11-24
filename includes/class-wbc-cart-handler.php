<?php
/**
 * Handle cart functionality for BOGO coupons
 *
 * @package WooCommerce_BOGO_Coupons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WBC_Cart_Handler Class
 */
class WBC_Cart_Handler {

    /**
     * Free item cart key prefix
     */
    const FREE_ITEM_KEY_PREFIX = 'bogo_free_';

    /**
     * Constructor
     */
    public function __construct() {
        // Auto add free products when items are added to cart
        add_action( 'woocommerce_add_to_cart', array( $this, 'check_and_add_free_products' ), 20, 6 );
        add_action( 'woocommerce_cart_item_removed', array( $this, 'remove_free_products_on_remove' ), 10, 2 );
        add_action( 'woocommerce_cart_item_quantity_updated', array( $this, 'update_free_products_on_quantity_change' ), 10, 4 );
        
        // Auto apply BOGO coupons
        add_action( 'woocommerce_add_to_cart', array( $this, 'auto_apply_bogo_coupons' ), 10, 6 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'check_and_apply_bogo_coupons' ), 5 );
        
        // Process BOGO coupons
        add_action( 'woocommerce_applied_coupon', array( $this, 'process_bogo_coupon' ), 10, 1 );
        add_action( 'woocommerce_removed_coupon', array( $this, 'remove_bogo_free_products' ), 10, 1 );
        
        // Mark free items in cart
        add_filter( 'woocommerce_cart_item_price', array( $this, 'display_free_item_price' ), 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'display_free_item_subtotal' ), 10, 3 );
        
        // Prevent editing quantity of free items
        add_filter( 'woocommerce_cart_item_quantity', array( $this, 'disable_quantity_field_for_free_items' ), 10, 3 );
        
        // Add cart item data
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 3 );
        
        // Calculate free item discounts
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_free_item_discounts' ), 10, 1 );
        
        // Validate cart
        add_action( 'woocommerce_check_cart_items', array( $this, 'validate_bogo_items' ) );
        
        // Track usage when order is placed
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_bogo_usage' ), 10, 3 );
    }

    /**
     * Check and add free products when items are added
     */
    public function check_and_add_free_products( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        // Don't process if this is already a free item
        if ( isset( $cart_item_data['bogo_free_item'] ) ) {
            return;
        }

        // Check if auto-add is enabled
        if ( get_option( 'wbc_enable_auto_add', 'yes' ) !== 'yes' ) {
            return;
        }

        // Debug logging
        if ( class_exists( 'WBC_Debug' ) ) {
            WBC_Debug::log( 'Check and add free products triggered', array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity
            ) );
        }

        // Get all active BOGO rules (automatic mode)
        // TODO: Implement automatic BOGO rules without coupon codes
        // $this->process_automatic_bogo_rules( $product_id, $variation_id );

        // Check all applied BOGO coupons
        foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
            $coupon = new WC_Coupon( $coupon_code );
            
            if ( $coupon->get_discount_type() !== 'bogo_coupon' ) {
                continue;
            }

            $this->process_bogo_rules_for_product( $coupon, $product_id, $variation_id );
        }
    }

    /**
     * Process BOGO coupon when applied
     */
    public function process_bogo_coupon( $coupon_code ) {
        $coupon = new WC_Coupon( $coupon_code );
        
        if ( $coupon->get_discount_type() !== 'bogo_coupon' ) {
            return;
        }

        // Debug logging
        if ( class_exists( 'WBC_Debug' ) ) {
            WBC_Debug::log( 'Processing BOGO coupon', array(
                'coupon_code' => $coupon_code,
                'coupon_id' => $coupon->get_id()
            ) );
        }

        // Check if auto-add is enabled
        if ( get_option( 'wbc_enable_auto_add', 'yes' ) !== 'yes' ) {
            wc_add_notice( __( 'BOGO auto-add is disabled in settings.', 'woo-bogo-coupons' ), 'notice' );
            return;
        }

        // Get BOGO rules to check if any exist
        $handler = new WBC_Coupon_Handler();
        $rules = $handler->get_bogo_rules( $coupon->get_id() );
        
        if ( empty( $rules ) ) {
            wc_add_notice( __( 'No BOGO rules found for this coupon.', 'woo-bogo-coupons' ), 'error' );
            return;
        }

        // Debug: Log rules
        if ( class_exists( 'WBC_Debug' ) ) {
            WBC_Debug::log( 'BOGO rules found', array( 'rules_count' => count( $rules ) ) );
        }

        // Process all cart items for BOGO rules
        $processed = false;
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['bogo_free_item'] ) ) {
                continue;
            }

            $product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
            $this->process_bogo_rules_for_product( $coupon, $cart_item['product_id'], $cart_item['variation_id'] );
            $processed = true;
        }
        
        if ( ! $processed ) {
            wc_add_notice( __( 'No eligible products in cart for BOGO offer.', 'woo-bogo-coupons' ), 'notice' );
        }
    }

    /**
     * Process BOGO rules for a specific product
     */
    private function process_bogo_rules_for_product( $coupon, $product_id, $variation_id = 0 ) {
        $handler = new WBC_Coupon_Handler();
        $rules = $handler->get_bogo_rules( $coupon->get_id() );

        if ( empty( $rules ) ) {
            return;
        }

        // Debug logging
        if ( class_exists( 'WBC_Debug' ) ) {
            WBC_Debug::log( 'Processing BOGO rules for product', array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'rules_count' => count( $rules )
            ) );
        }

        foreach ( $rules as $rule ) {
            $rule_product_id = intval( $rule->buy_product_id );
            $cart_product_id = intval( $product_id );
            $cart_variation_id = intval( $variation_id );
            
            // Debug: Log rule details
            if ( class_exists( 'WBC_Debug' ) ) {
                WBC_Debug::log( 'Checking rule', array(
                    'rule_buy_product' => $rule_product_id,
                    'rule_get_product' => $rule->get_product_id,
                    'cart_product' => $cart_product_id,
                    'cart_variation' => $cart_variation_id
                ) );
            }
            
            // Check if rule matches either the product ID or variation ID
            $matches = false;
            
            // If rule is for a variation, match exactly
            if ( $this->is_variation( $rule_product_id ) ) {
                $matches = ( $rule_product_id === $cart_variation_id );
            }
            // If rule is for a parent product, match any of its variations or the product itself
            else {
                $matches = ( $rule_product_id === $cart_product_id );
                
                // Also check if this is a variation of the rule product
                if ( ! $matches && $cart_variation_id > 0 ) {
                    $parent_id = wp_get_post_parent_id( $cart_variation_id );
                    $matches = ( $rule_product_id === intval( $parent_id ) );
                }
            }
            
            if ( ! $matches ) {
                if ( class_exists( 'WBC_Debug' ) ) {
                    WBC_Debug::log( 'Rule does not match product' );
                }
                continue;
            }

            // Calculate how many free items to add
            $buy_qty = $this->get_product_quantity_in_cart( $rule->buy_product_id );
            $free_qty = $this->get_free_product_quantity_in_cart( $rule->get_product_id, $coupon->get_code() );
            
            // Debug: Log quantities
            if ( class_exists( 'WBC_Debug' ) ) {
                WBC_Debug::log( 'Quantity check', array(
                    'buy_qty_in_cart' => $buy_qty,
                    'required_buy_qty' => $rule->buy_quantity,
                    'free_qty_in_cart' => $free_qty,
                    'rule_get_qty' => $rule->get_quantity
                ) );
            }
            
            if ( $buy_qty >= $rule->buy_quantity ) {
                $eligible_free_qty = floor( $buy_qty / $rule->buy_quantity ) * $rule->get_quantity;
                
                // Apply max free quantity limit if set
                if ( $rule->max_free_quantity > 0 ) {
                    $eligible_free_qty = min( $eligible_free_qty, $rule->max_free_quantity );
                }

                $qty_to_add = $eligible_free_qty - $free_qty;

                if ( class_exists( 'WBC_Debug' ) ) {
                    WBC_Debug::log( 'Free product calculation', array(
                        'eligible_free_qty' => $eligible_free_qty,
                        'qty_to_add' => $qty_to_add
                    ) );
                }

                if ( $qty_to_add > 0 ) {
                    $this->add_free_product_to_cart( $rule->get_product_id, $qty_to_add, $rule, $coupon->get_code() );
                } elseif ( $qty_to_add < 0 ) {
                    $this->update_free_product_quantity( $rule->get_product_id, $eligible_free_qty, $coupon->get_code() );
                }
            }
        }
    }

    /**
     * Add free product to cart
     */
    private function add_free_product_to_cart( $product_id, $quantity, $rule, $coupon_code ) {
        $product = wc_get_product( $product_id );
        
        if ( ! $product || ! $product->is_in_stock() ) {
            wc_add_notice( 
                sprintf( 
                    __( 'The free product "%s" is out of stock and cannot be added.', 'woo-bogo-coupons' ), 
                    $product ? esc_html( $product->get_name() ) : __( 'Product', 'woo-bogo-coupons' ) 
                ), 
                'error' 
            );
            return;
        }

        $cart_item_data = array(
            'bogo_free_item' => true,
            'bogo_coupon_code' => $coupon_code,
            'bogo_rule_id' => $rule->id,
            'bogo_discount_percentage' => $rule->get_discount_percentage,
        );

        // Remove action to prevent infinite loop
        remove_action( 'woocommerce_add_to_cart', array( $this, 'check_and_add_free_products' ), 20 );
        
        // Handle variations
        $variation_id = 0;
        $variation = array();
        
        if ( $product->get_type() === 'variation' ) {
            $variation_id = $product_id;
            $product_id = $product->get_parent_id();
            
            // Get variation attributes
            $variation = $product->get_variation_attributes();
        }
        
        WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
        
        // Re-add action
        add_action( 'woocommerce_add_to_cart', array( $this, 'check_and_add_free_products' ), 20, 6 );
        
        wc_add_notice( 
            sprintf( 
                __( 'Free product "%s" has been added to your cart!', 'woo-bogo-coupons' ), 
                esc_html( $product->get_name() ) 
            ), 
            'success' 
        );
    }

    /**
     * Update free product quantity
     */
    private function update_free_product_quantity( $product_id, $new_quantity, $coupon_code ) {
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['bogo_free_item'] ) && 
                 $cart_item['bogo_coupon_code'] === $coupon_code &&
                 $cart_item['product_id'] == $product_id ) {
                
                if ( $new_quantity > 0 ) {
                    WC()->cart->set_quantity( $cart_item_key, $new_quantity );
                } else {
                    WC()->cart->remove_cart_item( $cart_item_key );
                }
                break;
            }
        }
    }

    /**
     * Remove free products when coupon is removed
     */
    public function remove_bogo_free_products( $coupon_code ) {
        $coupon = new WC_Coupon( $coupon_code );
        
        if ( $coupon->get_discount_type() !== 'bogo_coupon' ) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['bogo_free_item'] ) && $cart_item['bogo_coupon_code'] === $coupon_code ) {
                WC()->cart->remove_cart_item( $cart_item_key );
            }
        }
    }

    /**
     * Remove free products when qualifying item is removed
     */
    public function remove_free_products_on_remove( $cart_item_key, $cart ) {
        $removed_item = $cart->removed_cart_contents[ $cart_item_key ];
        
        // If removed item is a free item, no action needed
        if ( isset( $removed_item['bogo_free_item'] ) ) {
            return;
        }

        // Re-validate all BOGO rules
        $this->validate_and_update_free_products();
    }

    /**
     * Update free products when quantity changes
     */
    public function update_free_products_on_quantity_change( $cart_item_key, $quantity, $old_quantity, $cart ) {
        $cart_item = $cart->cart_contents[ $cart_item_key ];
        
        // Don't process free items
        if ( isset( $cart_item['bogo_free_item'] ) ) {
            return;
        }

        $this->validate_and_update_free_products();
    }

    /**
     * Validate and update all free products
     */
    private function validate_and_update_free_products() {
        // First, remove all free items
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['bogo_free_item'] ) ) {
                WC()->cart->remove_cart_item( $cart_item_key );
            }
        }

        // Then re-apply all BOGO coupons
        foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
            $this->process_bogo_coupon( $coupon_code );
        }
    }

    /**
     * Get product quantity in cart (excluding free items)
     */
    private function get_product_quantity_in_cart( $product_id ) {
        $quantity = 0;
        $product_id = intval( $product_id );
        
        // Check if the product is a variation or parent product
        $is_variation = $this->is_variation( $product_id );
        
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['bogo_free_item'] ) ) {
                continue;
            }
            
            $cart_product_id = intval( $cart_item['product_id'] );
            $cart_variation_id = intval( $cart_item['variation_id'] );
            
            // If rule is for a specific variation, match exactly
            if ( $is_variation ) {
                if ( $cart_variation_id === $product_id ) {
                    $quantity += $cart_item['quantity'];
                }
            }
            // If rule is for parent product, count all its variations
            else {
                if ( $cart_product_id === $product_id ) {
                    $quantity += $cart_item['quantity'];
                }
                // Also check if cart item is a variation of this parent product
                elseif ( $cart_variation_id > 0 ) {
                    $parent_id = wp_get_post_parent_id( $cart_variation_id );
                    if ( intval( $parent_id ) === $product_id ) {
                        $quantity += $cart_item['quantity'];
                    }
                }
            }
        }
        
        return $quantity;
    }

    /**
     * Get free product quantity in cart
     */
    private function get_free_product_quantity_in_cart( $product_id, $coupon_code ) {
        $quantity = 0;
        $product_id = intval( $product_id );
        
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['bogo_free_item'] ) && 
                 $cart_item['bogo_coupon_code'] === $coupon_code &&
                 intval( $cart_item['product_id'] ) === $product_id ) {
                $quantity += $cart_item['quantity'];
            }
        }
        
        return $quantity;
    }

    /**
     * Display free item price
     */
    public function display_free_item_price( $price, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['bogo_free_item'] ) && $cart_item['bogo_discount_percentage'] == 100 ) {
            return '<span class="bogo-free-price">' . esc_html( get_option( 'wbc_free_product_text', __( 'FREE - BOGO Offer', 'woo-bogo-coupons' ) ) ) . '</span>';
        }
        return $price;
    }

    /**
     * Display free item subtotal
     */
    public function display_free_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['bogo_free_item'] ) && $cart_item['bogo_discount_percentage'] == 100 ) {
            return '<span class="bogo-free-subtotal">' . esc_html( get_option( 'wbc_free_product_text', __( 'FREE - BOGO Offer', 'woo-bogo-coupons' ) ) ) . '</span>';
        }
        return $subtotal;
    }

    /**
     * Disable quantity field for free items
     */
    public function disable_quantity_field_for_free_items( $quantity_html, $cart_item_key, $cart_item ) {
        if ( isset( $cart_item['bogo_free_item'] ) ) {
            return $cart_item['quantity'];
        }
        return $quantity_html;
    }

    /**
     * Add cart item data
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $cart_item_data['bogo_free_item'] ) ) {
            $cart_item_data['unique_key'] = md5( microtime() . rand() );
        }
        return $cart_item_data;
    }

    /**
     * Get cart item from session
     */
    public function get_cart_item_from_session( $cart_item, $values, $key ) {
        if ( isset( $values['bogo_free_item'] ) ) {
            $cart_item['bogo_free_item'] = $values['bogo_free_item'];
            $cart_item['bogo_coupon_code'] = $values['bogo_coupon_code'];
            $cart_item['bogo_rule_id'] = $values['bogo_rule_id'];
            $cart_item['bogo_discount_percentage'] = $values['bogo_discount_percentage'];
        }
        return $cart_item;
    }

    /**
     * Apply free item discounts
     */
    public function apply_free_item_discounts( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['bogo_free_item'] ) && isset( $cart_item['bogo_discount_percentage'] ) ) {
                $product = $cart_item['data'];
                $original_price = $product->get_price();
                
                // Validate discount percentage
                $discount_percentage = absint( $cart_item['bogo_discount_percentage'] );
                if ( $discount_percentage < 0 || $discount_percentage > 100 ) {
                    $discount_percentage = 0;
                }
                
                $discounted_price = $original_price * ( 1 - ( $discount_percentage / 100 ) );
                $product->set_price( $discounted_price );
            }
        }
    }

    /**
     * Validate BOGO items
     */
    public function validate_bogo_items() {
        $has_changes = false;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! isset( $cart_item['bogo_free_item'] ) ) {
                continue;
            }

            // Check if coupon still exists
            if ( ! in_array( $cart_item['bogo_coupon_code'], WC()->cart->get_applied_coupons() ) ) {
                WC()->cart->remove_cart_item( $cart_item_key );
                $has_changes = true;
                continue;
            }

            // Validate rule still applies
            $coupon = new WC_Coupon( $cart_item['bogo_coupon_code'] );
            $handler = new WBC_Coupon_Handler();
            $rules = $handler->get_bogo_rules( $coupon->get_id() );
            
            $rule_valid = false;
            foreach ( $rules as $rule ) {
                if ( $rule->id == $cart_item['bogo_rule_id'] ) {
                    $buy_qty = $this->get_product_quantity_in_cart( $rule->buy_product_id );
                    if ( $buy_qty >= $rule->buy_quantity ) {
                        $rule_valid = true;
                        break;
                    }
                }
            }

            if ( ! $rule_valid ) {
                WC()->cart->remove_cart_item( $cart_item_key );
                $has_changes = true;
            }
        }

        if ( $has_changes ) {
            WC()->cart->calculate_totals();
        }
    }

    /**
     * Check if product ID is a variation
     */
    private function is_variation( $product_id ) {
        $product = wc_get_product( $product_id );
        return $product && $product->get_type() === 'variation';
    }

    /**
     * Auto apply BOGO coupons when product is added to cart
     */
    public function auto_apply_bogo_coupons( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        // Don't process if this is a free item
        if ( isset( $cart_item_data['bogo_free_item'] ) ) {
            return;
        }

        // Check if auto-apply coupons is enabled
        if ( get_option( 'wbc_auto_apply_coupons', 'yes' ) !== 'yes' ) {
            return;
        }

        $this->check_and_apply_bogo_coupons();
    }

    /**
     * Check cart and automatically apply BOGO coupons
     */
    public function check_and_apply_bogo_coupons() {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }

        // Don't run during cart calculations to avoid infinite loops
        if ( did_action( 'woocommerce_before_calculate_totals' ) > 1 ) {
            return;
        }

        global $wpdb;

        // Get all BOGO coupons
        $bogo_coupons = $this->get_all_bogo_coupons();
        
        if ( empty( $bogo_coupons ) ) {
            return;
        }

        // Check each BOGO coupon
        foreach ( $bogo_coupons as $coupon ) {
            // Skip if coupon is already applied
            if ( WC()->cart->has_discount( $coupon->get_code() ) ) {
                continue;
            }

            // Check if coupon should be auto-applied
            if ( ! $this->should_auto_apply_coupon( $coupon ) ) {
                continue;
            }

            // Get rules for this coupon
            $rules = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wbc_bogo_rules WHERE coupon_id = %d",
                $coupon->get_id()
            ) );

            if ( $rules === false || empty( $rules ) ) {
                continue;
            }

            // Check if any rule matches cart contents
            $should_apply = false;
            foreach ( $rules as $rule ) {
                $qty_in_cart = $this->get_product_quantity_in_cart( $rule->buy_product_id );
                if ( $qty_in_cart >= $rule->buy_quantity ) {
                    $should_apply = true;
                    break;
                }
            }

            if ( $should_apply ) {
                // Apply the coupon
                WC()->cart->apply_coupon( $coupon->get_code() );
                
                // Add notice
                wc_add_notice( 
                    sprintf( 
                        __( 'BOGO offer "%s" has been automatically applied!', 'woo-bogo-coupons' ), 
                        $coupon->get_code() 
                    ), 
                    'success' 
                );
            }
        }

        // Also remove BOGO coupons that no longer qualify
        foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
            $coupon = new WC_Coupon( $coupon_code );
            
            if ( $coupon->get_discount_type() !== 'bogo_coupon' ) {
                continue;
            }

            // Check if coupon should be auto-removed
            if ( ! $this->should_auto_apply_coupon( $coupon ) ) {
                continue;
            }

            // Check if coupon still qualifies
            $rules = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wbc_bogo_rules WHERE coupon_id = %d",
                $coupon->get_id()
            ) );

            if ( $rules === false ) {
                continue;
            }

            $still_qualifies = false;
            foreach ( $rules as $rule ) {
                $qty_in_cart = $this->get_product_quantity_in_cart( $rule->buy_product_id );
                if ( $qty_in_cart >= $rule->buy_quantity ) {
                    $still_qualifies = true;
                    break;
                }
            }

            if ( ! $still_qualifies ) {
                WC()->cart->remove_coupon( $coupon_code );
                wc_add_notice( 
                    sprintf( 
                        __( 'BOGO offer "%s" has been removed as you no longer qualify.', 'woo-bogo-coupons' ), 
                        $coupon_code 
                    ), 
                    'notice' 
                );
            }
        }
    }

    /**
     * Get all BOGO coupons
     */
    private function get_all_bogo_coupons() {
        $args = array(
            'posts_per_page' => -1,
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'discount_type',
                    'value'   => 'bogo_coupon',
                    'compare' => '='
                )
            )
        );

        $coupons = get_posts( $args );
        $bogo_coupons = array();

        foreach ( $coupons as $coupon_post ) {
            $coupon = new WC_Coupon( $coupon_post->ID );
            if ( $coupon->get_discount_type() === 'bogo_coupon' && $coupon->is_valid() ) {
                $bogo_coupons[] = $coupon;
            }
        }

        return $bogo_coupons;
    }

    /**
     * Check if coupon should be auto-applied
     */
    private function should_auto_apply_coupon( $coupon ) {
        // Check global setting
        if ( get_option( 'wbc_auto_apply_coupons', 'yes' ) !== 'yes' ) {
            return false;
        }

        // Check individual coupon setting (if implemented)
        $auto_apply = get_post_meta( $coupon->get_id(), 'wbc_auto_apply', true );
        
        // If not set, default to true
        if ( $auto_apply === '' ) {
            return true;
        }

        return $auto_apply === 'yes';
    }

    /**
     * Track BOGO usage when order is placed
     */
    public function track_bogo_usage( $order_id, $posted_data, $order ) {
        global $wpdb;

        $cart = WC()->cart;
        if ( ! $cart ) {
            return;
        }

        // Get user ID
        $user_id = $order->get_user_id();

        // Track each BOGO free item
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item['bogo_free_item'] ) || ! isset( $cart_item['bogo_rule_id'] ) ) {
                continue;
            }

            // Insert usage record
            $wpdb->insert(
                $wpdb->prefix . 'wbc_bogo_usage',
                array(
                    'rule_id' => absint( $cart_item['bogo_rule_id'] ),
                    'order_id' => absint( $order_id ),
                    'user_id' => $user_id ? absint( $user_id ) : null,
                    'free_quantity' => absint( $cart_item['quantity'] ),
                ),
                array( '%d', '%d', '%d', '%d' )
            );
        }
    }
}