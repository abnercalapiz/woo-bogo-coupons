<?php
/**
 * BOGO Reports Page
 *
 * @package WooCommerce_BOGO_Coupons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check user capabilities
if ( ! current_user_can( 'view_wbc_bogo_reports' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woo-bogo-coupons' ) );
}

// Get usage data
global $wpdb;

$usage_data = $wpdb->get_results( 
    $wpdb->prepare( "
        SELECT 
            r.*, 
            u.order_id,
            u.free_quantity,
            u.used_at,
            c.post_title as coupon_name,
            p1.post_title as buy_product_name,
            p2.post_title as get_product_name
        FROM {$wpdb->prefix}wbc_bogo_usage u
        JOIN {$wpdb->prefix}wbc_bogo_rules r ON u.rule_id = r.id
        JOIN {$wpdb->posts} c ON r.coupon_id = c.ID
        JOIN {$wpdb->posts} p1 ON r.buy_product_id = p1.ID
        JOIN {$wpdb->posts} p2 ON r.get_product_id = p2.ID
        ORDER BY u.used_at DESC
        LIMIT %d
    ", 100 )
);

?>
<div class="wrap">
    <h1><?php esc_html_e( 'BOGO Coupon Reports', 'woo-bogo-coupons' ); ?></h1>
    
    <div class="postbox">
        <h2 class="hndle"><?php esc_html_e( 'Recent BOGO Usage', 'woo-bogo-coupons' ); ?></h2>
        <div class="inside">
            <?php if ( $usage_data ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Order', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Coupon', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Buy Product', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Free Product', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Free Qty', 'woo-bogo-coupons' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'woo-bogo-coupons' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $usage_data as $usage ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $usage->order_id . '&action=edit' ) ); ?>">
                                        #<?php echo esc_html( $usage->order_id ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $usage->coupon_name ); ?></td>
                                <td><?php echo esc_html( $usage->buy_product_name ); ?></td>
                                <td><?php echo esc_html( $usage->get_product_name ); ?></td>
                                <td><?php echo esc_html( $usage->free_quantity ); ?></td>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $usage->used_at ) ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No BOGO usage data available yet.', 'woo-bogo-coupons' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Summary statistics
    $total_usage = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbc_bogo_usage" );
    $total_free_items = $wpdb->get_var( "SELECT SUM(free_quantity) FROM {$wpdb->prefix}wbc_bogo_usage" );
    $active_rules = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbc_bogo_rules" );
    ?>
    
    <div class="postbox">
        <h2 class="hndle"><?php esc_html_e( 'Summary', 'woo-bogo-coupons' ); ?></h2>
        <div class="inside">
            <ul>
                <li><?php printf( esc_html__( 'Total BOGO uses: %s', 'woo-bogo-coupons' ), '<strong>' . number_format_i18n( $total_usage ) . '</strong>' ); ?></li>
                <li><?php printf( esc_html__( 'Total free items given: %s', 'woo-bogo-coupons' ), '<strong>' . number_format_i18n( $total_free_items ) . '</strong>' ); ?></li>
                <li><?php printf( esc_html__( 'Active BOGO rules: %s', 'woo-bogo-coupons' ), '<strong>' . number_format_i18n( $active_rules ) . '</strong>' ); ?></li>
            </ul>
        </div>
    </div>
</div>