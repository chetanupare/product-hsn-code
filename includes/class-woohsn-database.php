<?php
/**
 * Database functionality for WooHSN
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Database {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_woohsn_add_hsn_code', array($this, 'ajax_add_hsn_code'));
        add_action('wp_ajax_woohsn_edit_hsn_code', array($this, 'ajax_edit_hsn_code'));
        add_action('wp_ajax_woohsn_delete_hsn_code', array($this, 'ajax_delete_hsn_code'));
        add_action('wp_ajax_woohsn_get_hsn_codes', array($this, 'ajax_get_hsn_codes'));
        add_action('wp_ajax_woohsn_get_hsn_info', array($this, 'ajax_get_hsn_info'));
        add_action('wp_ajax_woohsn_bulk_delete_hsn', array($this, 'ajax_bulk_delete_hsn'));
        add_action('woohsn_daily_cleanup', array($this, 'daily_cleanup'));
    }
    
    /**
     * AJAX add HSN code
     */
    public function ajax_add_hsn_code() {
        check_ajax_referer('woohsn_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn'));
        }
        
        $hsn_code = sanitize_text_field($_POST['hsn_code']);
        $description = sanitize_textarea_field($_POST['description']);
        $gst_rate = floatval($_POST['gst_rate']);
        
        if (empty($hsn_code)) {
            wp_send_json_error(__('HSN code is required.', 'woohsn'));
        }
        
        global $wpdb;
        
        // Check if HSN code already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
            $hsn_code
        ));
        
        if ($existing) {
            wp_send_json_error(__('HSN code already exists.', 'woohsn'));
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'woohsn_codes',
            array(
                'hsn_code' => $hsn_code,
                'description' => $description,
                'gst_rate' => $gst_rate
            ),
            array('%s', '%s', '%f')
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to add HSN code.', 'woohsn'));
        }
        
        // Clear cache
        $this->clear_hsn_cache($hsn_code);
        
        wp_send_json_success(array(
            'message' => __('HSN code added successfully.', 'woohsn'),
            'id' => $wpdb->insert_id
        ));
    }
    
    /**
     * Get HSN statistics
     */
    public function get_hsn_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total HSN codes
        $stats['total_hsn_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_codes");
        
        // HSN codes by GST rate
        $gst_breakdown = $wpdb->get_results(
            "SELECT gst_rate, COUNT(*) as count FROM {$wpdb->prefix}woohsn_codes GROUP BY gst_rate ORDER BY gst_rate"
        );
        
        $stats['gst_rate_breakdown'] = array();
        foreach ($gst_breakdown as $item) {
            $stats['gst_rate_breakdown'][] = array(
                'rate' => $item->gst_rate,
                'count' => $item->count
            );
        }
        
        // Products with HSN codes
        $stats['products_with_hsn'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'woohsn_code' AND meta_value != ''"
        );
        
        // Products without HSN codes
        $total_products = wp_count_posts('product')->publish;
        $stats['products_without_hsn'] = $total_products - $stats['products_with_hsn'];
        
        // Completion percentage
        $stats['completion_percentage'] = $total_products > 0 ? round(($stats['products_with_hsn'] / $total_products) * 100, 2) : 0;
        
        // Recent activity
        $stats['recent_imports'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_logs WHERE operation_type = 'import' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        $stats['recent_exports'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_logs WHERE operation_type = 'export' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        return $stats;
    }
    
    /**
     * Clear HSN cache
     */
    private function clear_hsn_cache($hsn_code) {
        delete_transient('woohsn_gst_rate_' . $hsn_code);
        
        // Clear object cache if available
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('woohsn_gst_rate_' . $hsn_code);
        }
    }
    
    /**
     * Daily cleanup routine
     */
    public function daily_cleanup() {
        global $wpdb;
        
        // Clean up old log entries (older than 90 days)
        $wpdb->query("DELETE FROM {$wpdb->prefix}woohsn_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Optimize database tables
        $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}woohsn_codes");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}woohsn_logs");
    }
}