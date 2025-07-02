<?php
/**
 * Database functionality for WooHSN Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Pro_Database {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_woohsn_pro_add_hsn_code', array($this, 'ajax_add_hsn_code'));
        add_action('wp_ajax_woohsn_pro_edit_hsn_code', array($this, 'ajax_edit_hsn_code'));
        add_action('wp_ajax_woohsn_pro_delete_hsn_code', array($this, 'ajax_delete_hsn_code'));
        add_action('wp_ajax_woohsn_pro_get_hsn_codes', array($this, 'ajax_get_hsn_codes'));
        add_action('wp_ajax_woohsn_pro_get_hsn_info', array($this, 'ajax_get_hsn_info'));
        add_action('wp_ajax_woohsn_pro_bulk_delete_hsn', array($this, 'ajax_bulk_delete_hsn'));
        add_action('woohsn_pro_daily_cleanup', array($this, 'daily_cleanup'));
    }
    
    /**
     * AJAX add HSN code
     */
    public function ajax_add_hsn_code() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        $hsn_code = sanitize_text_field($_POST['hsn_code']);
        $description = sanitize_textarea_field($_POST['description']);
        $gst_rate = floatval($_POST['gst_rate']);
        
        if (empty($hsn_code)) {
            wp_send_json_error(__('HSN code is required.', 'woohsn-pro'));
        }
        
        global $wpdb;
        
        // Check if HSN code already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}woohsn_pro_codes WHERE hsn_code = %s",
            $hsn_code
        ));
        
        if ($existing) {
            wp_send_json_error(__('HSN code already exists.', 'woohsn-pro'));
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'woohsn_pro_codes',
            array(
                'hsn_code' => $hsn_code,
                'description' => $description,
                'gst_rate' => $gst_rate
            ),
            array('%s', '%s', '%f')
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to add HSN code.', 'woohsn-pro'));
        }
        
        // Clear cache
        $this->clear_hsn_cache($hsn_code);
        
        wp_send_json_success(array(
            'message' => __('HSN code added successfully.', 'woohsn-pro'),
            'id' => $wpdb->insert_id
        ));
    }
    
    /**
     * AJAX edit HSN code
     */
    public function ajax_edit_hsn_code() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        $id = intval($_POST['id']);
        $hsn_code = sanitize_text_field($_POST['hsn_code']);
        $description = sanitize_textarea_field($_POST['description']);
        $gst_rate = floatval($_POST['gst_rate']);
        
        if (empty($hsn_code)) {
            wp_send_json_error(__('HSN code is required.', 'woohsn-pro'));
        }
        
        global $wpdb;
        
        // Get old HSN code for cache clearing
        $old_hsn_code = $wpdb->get_var($wpdb->prepare(
            "SELECT hsn_code FROM {$wpdb->prefix}woohsn_pro_codes WHERE id = %d",
            $id
        ));
        
        // Check if new HSN code already exists (excluding current record)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}woohsn_pro_codes WHERE hsn_code = %s AND id != %d",
            $hsn_code,
            $id
        ));
        
        if ($existing) {
            wp_send_json_error(__('HSN code already exists.', 'woohsn-pro'));
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'woohsn_pro_codes',
            array(
                'hsn_code' => $hsn_code,
                'description' => $description,
                'gst_rate' => $gst_rate
            ),
            array('id' => $id),
            array('%s', '%s', '%f'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to update HSN code.', 'woohsn-pro'));
        }
        
        // Clear cache for both old and new HSN codes
        $this->clear_hsn_cache($old_hsn_code);
        if ($old_hsn_code !== $hsn_code) {
            $this->clear_hsn_cache($hsn_code);
        }
        
        wp_send_json_success(__('HSN code updated successfully.', 'woohsn-pro'));
    }
    
    /**
     * AJAX delete HSN code
     */
    public function ajax_delete_hsn_code() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        $id = intval($_POST['id']);
        
        global $wpdb;
        
        // Get HSN code for cache clearing
        $hsn_code = $wpdb->get_var($wpdb->prepare(
            "SELECT hsn_code FROM {$wpdb->prefix}woohsn_pro_codes WHERE id = %d",
            $id
        ));
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'woohsn_pro_codes',
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to delete HSN code.', 'woohsn-pro'));
        }
        
        // Clear cache
        $this->clear_hsn_cache($hsn_code);
        
        wp_send_json_success(__('HSN code deleted successfully.', 'woohsn-pro'));
    }
    
    /**
     * AJAX get HSN codes (for DataTables)
     */
    public function ajax_get_hsn_codes() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        global $wpdb;
        
        $draw = intval($_POST['draw']);
        $start = intval($_POST['start']);
        $length = intval($_POST['length']);
        $search_value = sanitize_text_field($_POST['search']['value']);
        
        $order_column_index = intval($_POST['order'][0]['column']);
        $order_direction = sanitize_text_field($_POST['order'][0]['dir']);
        
        $columns = array('hsn_code', 'description', 'gst_rate', 'created_at');
        $order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'hsn_code';
        
        // Build query
        $where = '';
        $search_params = array();
        
        if (!empty($search_value)) {
            $where = " WHERE hsn_code LIKE %s OR description LIKE %s";
            $search_params = array(
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%'
            );
        }
        
        // Count total records
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_pro_codes");
        
        // Count filtered records
        if (!empty($search_value)) {
            $filtered_records = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_pro_codes $where",
                ...$search_params
            ));
        } else {
            $filtered_records = $total_records;
        }
        
        // Get data
        $query = "SELECT * FROM {$wpdb->prefix}woohsn_pro_codes $where ORDER BY $order_column $order_direction LIMIT $start, $length";
        
        if (!empty($search_value)) {
            $data = $wpdb->get_results($wpdb->prepare($query, ...$search_params));
        } else {
            $data = $wpdb->get_results($query);
        }
        
        $response = array(
            'draw' => $draw,
            'recordsTotal' => intval($total_records),
            'recordsFiltered' => intval($filtered_records),
            'data' => array()
        );
        
        foreach ($data as $row) {
            $response['data'][] = array(
                'id' => $row->id,
                'hsn_code' => $row->hsn_code,
                'description' => $row->description,
                'gst_rate' => $row->gst_rate,
                'created_at' => date('Y-m-d H:i', strtotime($row->created_at)),
                'actions' => $this->get_action_buttons($row->id)
            );
        }
        
        wp_send_json($response);
    }
    
    /**
     * AJAX get HSN info
     */
    public function ajax_get_hsn_info() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        $hsn_code = sanitize_text_field($_POST['hsn_code']);
        
        global $wpdb;
        
        $hsn_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woohsn_pro_codes WHERE hsn_code = %s",
            $hsn_code
        ));
        
        if ($hsn_info) {
            wp_send_json_success($hsn_info);
        } else {
            wp_send_json_error(__('HSN code not found.', 'woohsn-pro'));
        }
    }
    
    /**
     * AJAX bulk delete HSN codes
     */
    public function ajax_bulk_delete_hsn() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        $ids = array_map('intval', $_POST['ids']);
        
        if (empty($ids)) {
            wp_send_json_error(__('No HSN codes selected.', 'woohsn-pro'));
        }
        
        global $wpdb;
        
        // Get HSN codes for cache clearing
        $hsn_codes = $wpdb->get_col($wpdb->prepare(
            "SELECT hsn_code FROM {$wpdb->prefix}woohsn_pro_codes WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
            ...$ids
        ));
        
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}woohsn_pro_codes WHERE id IN ($placeholders)",
            ...$ids
        ));
        
        if ($result === false) {
            wp_send_json_error(__('Failed to delete HSN codes.', 'woohsn-pro'));
        }
        
        // Clear cache for all deleted HSN codes
        foreach ($hsn_codes as $hsn_code) {
            $this->clear_hsn_cache($hsn_code);
        }
        
        wp_send_json_success(sprintf(__('%d HSN codes deleted successfully.', 'woohsn-pro'), $result));
    }
    
    /**
     * Daily cleanup routine
     */
    public function daily_cleanup() {
        global $wpdb;
        
        // Clean up old log entries (older than 90 days)
        $wpdb->query("DELETE FROM {$wpdb->prefix}woohsn_pro_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Clean up expired transients
        $this->cleanup_expired_transients();
        
        // Optimize database tables
        $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}woohsn_pro_codes");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}woohsn_pro_logs");
    }
    
    /**
     * Get action buttons for HSN code row
     */
    private function get_action_buttons($id) {
        $edit_button = '<button class="button button-small woohsn-pro-edit-hsn" data-id="' . $id . '">' . __('Edit', 'woohsn-pro') . '</button>';
        $delete_button = '<button class="button button-small button-link-delete woohsn-pro-delete-hsn" data-id="' . $id . '">' . __('Delete', 'woohsn-pro') . '</button>';
        
        return $edit_button . ' ' . $delete_button;
    }
    
    /**
     * Clear HSN cache
     */
    private function clear_hsn_cache($hsn_code) {
        delete_transient('woohsn_pro_gst_rate_' . $hsn_code);
        
        // Clear object cache if available
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('woohsn_pro_gst_rate_' . $hsn_code);
        }
    }
    
    /**
     * Cleanup expired transients
     */
    private function cleanup_expired_transients() {
        global $wpdb;
        
        // Delete expired transients with our prefix
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_woohsn_pro_%' AND option_value < UNIX_TIMESTAMP()");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_woohsn_pro_%' AND option_name NOT IN (SELECT CONCAT('_transient_', SUBSTR(option_name, 20)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_woohsn_pro_%')");
    }
    
    /**
     * Get HSN statistics
     */
    public function get_hsn_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total HSN codes
        $stats['total_hsn_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_pro_codes");
        
        // HSN codes by GST rate
        $gst_breakdown = $wpdb->get_results(
            "SELECT gst_rate, COUNT(*) as count FROM {$wpdb->prefix}woohsn_pro_codes GROUP BY gst_rate ORDER BY gst_rate"
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
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'woohsn_pro_code' AND meta_value != ''"
        );
        
        // Products without HSN codes
        $total_products = wp_count_posts('product')->publish;
        $stats['products_without_hsn'] = $total_products - $stats['products_with_hsn'];
        
        // Completion percentage
        $stats['completion_percentage'] = $total_products > 0 ? round(($stats['products_with_hsn'] / $total_products) * 100, 2) : 0;
        
        // Recent activity
        $stats['recent_imports'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_pro_logs WHERE operation_type = 'import' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        $stats['recent_exports'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_pro_logs WHERE operation_type = 'export' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        return $stats;
    }
    
    /**
     * Backup HSN database
     */
    public function backup_hsn_database() {
        global $wpdb;
        
        $hsn_codes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woohsn_pro_codes ORDER BY hsn_code ASC");
        
        $upload_dir = wp_upload_dir();
        $woohsn_dir = $upload_dir['basedir'] . '/woohsn-pro';
        $filename = 'backup_hsn_database_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = $woohsn_dir . '/' . $filename;
        
        $backup_data = array(
            'version' => WOOHSN_PRO_VERSION,
            'created_at' => current_time('mysql'),
            'total_records' => count($hsn_codes),
            'data' => $hsn_codes
        );
        
        $json_data = json_encode($backup_data, JSON_PRETTY_PRINT);
        
        if (file_put_contents($filepath, $json_data)) {
            return $filename;
        }
        
        return false;
    }
    
    /**
     * Restore HSN database from backup
     */
    public function restore_hsn_database($filepath) {
        if (!file_exists($filepath)) {
            return new WP_Error('file_not_found', __('Backup file not found.', 'woohsn-pro'));
        }
        
        $json_data = file_get_contents($filepath);
        $backup_data = json_decode($json_data, true);
        
        if (!$backup_data || !isset($backup_data['data'])) {
            return new WP_Error('invalid_backup', __('Invalid backup file format.', 'woohsn-pro'));
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Clear existing data
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}woohsn_pro_codes");
            
            // Insert backup data
            foreach ($backup_data['data'] as $hsn_code) {
                $wpdb->insert(
                    $wpdb->prefix . 'woohsn_pro_codes',
                    array(
                        'hsn_code' => $hsn_code->hsn_code,
                        'description' => $hsn_code->description,
                        'gst_rate' => $hsn_code->gst_rate
                    ),
                    array('%s', '%s', '%f')
                );
            }
            
            $wpdb->query('COMMIT');
            
            // Clear all HSN-related cache
            $this->clear_all_hsn_cache();
            
            return array(
                'success' => true,
                'records_restored' => count($backup_data['data'])
            );
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('restore_failed', __('Failed to restore backup: ', 'woohsn-pro') . $e->getMessage());
        }
    }
    
    /**
     * Clear all HSN-related cache
     */
    private function clear_all_hsn_cache() {
        global $wpdb;
        
        // Delete all HSN-related transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_woohsn_pro_gst_rate_%' OR option_name LIKE '_transient_timeout_woohsn_pro_gst_rate_%'");
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('woohsn_pro');
        }
    }
}