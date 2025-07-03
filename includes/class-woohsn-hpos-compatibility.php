<?php
/**
 * HPOS (High-Performance Order Storage) compatibility for WooHSN
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_HPOS_Compatibility {
    
    /**
     * Check if HPOS is enabled
     */
    public static function is_hpos_enabled() {
        if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }
    
    /**
     * Check if a post/order ID is a WooCommerce order
     */
    public static function is_wc_order($post_id) {
        if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            return 'shop_order' === \Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($post_id);
        }
        
        // Fallback for when OrderUtil is not available
        return get_post_type($post_id) === 'shop_order';
    }
    
    /**
     * Get order object safely
     */
    public static function get_order($order_id) {
        return wc_get_order($order_id);
    }
    
    /**
     * Get order meta data safely
     */
    public static function get_order_meta($order_id, $meta_key, $single = true) {
        $order = self::get_order($order_id);
        if (!$order) {
            return $single ? '' : array();
        }
        
        return $order->get_meta($meta_key, $single);
    }
    
    /**
     * Update order meta data safely
     */
    public static function update_order_meta($order_id, $meta_key, $meta_value) {
        $order = self::get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $order->update_meta_data($meta_key, $meta_value);
        $order->save();
        
        return true;
    }
    
    /**
     * Add order meta data safely
     */
    public static function add_order_meta($order_id, $meta_key, $meta_value, $unique = false) {
        $order = self::get_order($order_id);
        if (!$order) {
            return false;
        }
        
        if ($unique && $order->get_meta($meta_key)) {
            return false;
        }
        
        $order->add_meta_data($meta_key, $meta_value, $unique);
        $order->save();
        
        return true;
    }
    
    /**
     * Delete order meta data safely
     */
    public static function delete_order_meta($order_id, $meta_key, $meta_value = '') {
        $order = self::get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $order->delete_meta_data($meta_key);
        $order->save();
        
        return true;
    }
    
    /**
     * Get orders query with HPOS compatibility
     */
    public static function get_orders($args = array()) {
        // Use WC_Order_Query for both HPOS and legacy
        $query = new WC_Order_Query($args);
        return $query->get_orders();
    }
    
    /**
     * Check if current screen is orders list (HPOS compatible)
     */
    public static function is_orders_screen() {
        if (!is_admin()) {
            return false;
        }
        
        global $current_screen;
        
        if (self::is_hpos_enabled()) {
            return $current_screen && $current_screen->id === 'woocommerce_page_wc-orders';
        } else {
            return $current_screen && $current_screen->id === 'edit-shop_order';
        }
    }
    
    /**
     * Get order edit URL (HPOS compatible)
     */
    public static function get_order_edit_url($order_id) {
        if (self::is_hpos_enabled() && class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url($order_id);
        } else {
            return admin_url('post.php?post=' . $order_id . '&action=edit');
        }
    }
    
    /**
     * Check if synchronization is enabled
     */
    public static function is_sync_enabled() {
        if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();
        }
        return false;
    }
    
    /**
     * Get supported features
     */
    public static function get_supported_features() {
        return array(
            'orders_crud' => true,
            'orders_meta' => true,
            'orders_query' => true,
            'hpos_enabled' => self::is_hpos_enabled(),
            'sync_enabled' => self::is_sync_enabled()
        );
    }
    
    /**
     * Log HPOS compatibility information
     */
    public static function log_hpos_info() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $features = self::get_supported_features();
            error_log('WooHSN HPOS Info: ' . wp_json_encode($features));
        }
    }
}