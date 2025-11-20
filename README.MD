=== Woo BOGO Coupons ===
Contributors: jezweb
Donate link: https://www.jezweb.com.au/
Tags: woocommerce, coupons, bogo, buy one get one, free products, auto-apply
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Jezweb
Author URI: https://www.jezweb.com.au/

Create powerful Buy One Get One (BOGO) coupons for WooCommerce with automatic coupon application. When customers add qualifying products, BOGO coupons apply automatically and free products are added to cart.

== Description ==

Woo BOGO Coupons extends WooCommerce's coupon system to allow you to create Buy One Get One (BOGO) offers with advanced automation. The plugin can automatically apply BOGO coupons when qualifying products are in the cart - no manual coupon entry required!

Developed by [Jezweb](https://www.jezweb.com.au/), a professional WordPress and WooCommerce development agency.

= Key Features =

**🎯 Automatic Coupon Application**
* BOGO coupons automatically apply when qualifying products are added to cart
* No need for customers to remember or enter coupon codes
* Coupons auto-remove when products no longer qualify

**🛍️ Flexible BOGO Rules**
* Create unlimited BOGO rules per coupon
* Set "Buy X Get Y" rules with custom quantities
* Support for percentage discounts (10%, 25%, 50%, 100% free)
* Set maximum free quantity limits per rule
* Works with simple and variable products

**⚙️ Advanced Configuration**
* Enable/disable auto-apply globally or per coupon
* Auto-add free products to cart instantly
* Product search with WooCommerce Select2 integration
* Full integration with WooCommerce validation rules

**📊 Management & Tracking**
* Usage tracking and reporting
* Debug tools for troubleshooting
* Settings panel in WooCommerce > Settings > BOGO Coupons

= How it works =

1. Create a new coupon in WooCommerce > Coupons
2. Select "BOGO (Buy One Get One)" as the discount type
3. Enable "Auto-apply this coupon" (checked by default)
4. Add one or more BOGO rules:
   - **Buy Product**: Select the product customers must buy
   - **Buy Qty**: Set the required purchase quantity
   - **Get Product**: Select the free/discounted product
   - **Get Qty**: Set the free product quantity
   - **Discount %**: Set discount percentage (100 = free)
   - **Max Free Qty**: Optional limit on free items
5. Save the coupon
6. When customers add qualifying products, the BOGO coupon applies automatically and free items are added to cart

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woo-bogo-coupons` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WooCommerce > Coupons to create your first BOGO coupon
4. Configure plugin settings at WooCommerce > Settings > BOGO Coupons

== Frequently Asked Questions ==

= Does this work with variable products? =

Yes! The plugin supports both simple and variable products. You can:
- Select a parent variable product to match ANY of its variations
- Select a specific variation to match ONLY that variation

= Can I create multiple rules for one coupon? =

Yes, you can add unlimited BOGO rules to a single coupon. Click "Add Rule" to add as many rules as needed. For example:
- Rule 1: Buy 1 Shirt → Get 1 Hat Free
- Rule 2: Buy 2 Pants → Get 1 Belt 50% off
- Rule 3: Buy 1 Jacket → Get 1 Scarf Free

= Do customers need to enter the coupon code? =

No! With auto-apply enabled (default), BOGO coupons automatically apply when qualifying products are in the cart. Customers don't need to remember or enter any codes.

= Can I offer partial discounts instead of 100% free? =

Yes, you can set any discount percentage from 0-100% for the free products. Common examples:
- 100% = Completely free
- 50% = Half price
- 25% = 25% off
- Any custom percentage

= Will the free products be removed if the coupon is removed? =

Yes, by default free products are automatically removed when the BOGO coupon is removed from the cart.

= Can I disable automatic coupon application? =

Yes, you can control this in two ways:
1. **Globally**: WooCommerce > Settings > BOGO Coupons > "Auto-apply BOGO Coupons"
2. **Per Coupon**: Uncheck "Auto-apply this coupon" when editing individual coupons

= What happens if products go out of stock? =

The plugin checks product availability before adding free items. Out-of-stock products won't be added, and customers see a notification.

= Can I limit the number of free products? =

Yes, use the "Max Free Qty" field in each rule to set limits. Leave blank for unlimited.

== Screenshots ==

1. BOGO coupon configuration with multiple rules
2. Auto-apply settings for automatic coupon application
3. Customer view - BOGO coupon automatically applied
4. Free products added to cart
5. Plugin settings page

== Changelog ==

= 1.0.0 =
* Initial release
* BOGO coupon type for WooCommerce
* Automatic coupon application feature
* Multiple rules per coupon support
* Percentage-based discounts
* Auto-add free products to cart
* Variable product support
* Usage tracking and reporting
* Full WooCommerce integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of Woo BOGO Coupons with automatic coupon application.

== Credits ==

Developed by [Jezweb](https://www.jezweb.com.au/) - Professional WordPress & WooCommerce Development