<?php
/**
 * Import/Export functionality for WooHSN Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Pro_Import_Export {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_woohsn_pro_import_csv', array($this, 'ajax_import_csv'));
        add_action('wp_ajax_woohsn_pro_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_woohsn_pro_import_hsn_database', array($this, 'ajax_import_hsn_database'));
        add_action('init', array($this, 'handle_export_download'));
    }
    
    /**
     * AJAX import CSV
     */
    public function ajax_import_csv() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded.', 'woohsn-pro'));
        }
        
        $file = $_FILES['csv_file'];
        
        // Validate file
        $validation_result = $this->validate_csv_file($file);
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        // Move file to secure location
        $upload_dir = wp_upload_dir();
        $woohsn_dir = $upload_dir['basedir'] . '/woohsn-pro';
        $filename = 'import_' . time() . '_' . sanitize_file_name($file['name']);
        $filepath = $woohsn_dir . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_error(__('Failed to upload file.', 'woohsn-pro'));
        }
        
        // Process CSV
        $result = $this->process_csv_import($filepath);
        
        // Clean up file
        unlink($filepath);
        
        // Log operation
        $this->log_operation('import', $filename, $result['total'], $result['success'], $result['errors']);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX export CSV
     */
    public function ajax_export_csv() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        $export_type = sanitize_text_field($_POST['export_type']);
        $include_empty = isset($_POST['include_empty']) ? true : false;
        
        switch ($export_type) {
            case 'products':
                $result = $this->export_products($include_empty);
                break;
            case 'hsn_database':
                $result = $this->export_hsn_database();
                break;
            default:
                wp_send_json_error(__('Invalid export type.', 'woohsn-pro'));
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Log operation
        $this->log_operation('export', $result['filename'], $result['count'], $result['count'], 0);
        
        wp_send_json_success(array(
            'download_url' => add_query_arg(array(
                'woohsn_pro_download' => wp_create_nonce('woohsn_pro_download'),
                'file' => urlencode($result['filename'])
            ), admin_url('admin.php'))
        ));
    }
    
    /**
     * AJAX import HSN database
     */
    public function ajax_import_hsn_database() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn-pro'));
        }
        
        if (!isset($_FILES['hsn_database_file'])) {
            wp_send_json_error(__('No file uploaded.', 'woohsn-pro'));
        }
        
        $file = $_FILES['hsn_database_file'];
        
        // Validate file
        $validation_result = $this->validate_csv_file($file);
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        // Process HSN database import
        $result = $this->process_hsn_database_import($file['tmp_name']);
        
        wp_send_json_success($result);
    }
    
    /**
     * Handle export download
     */
    public function handle_export_download() {
        if (isset($_GET['woohsn_pro_download']) && isset($_GET['file'])) {
            if (!wp_verify_nonce($_GET['woohsn_pro_download'], 'woohsn_pro_download')) {
                wp_die(__('Invalid download link.', 'woohsn-pro'));
            }
            
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to download this file.', 'woohsn-pro'));
            }
            
            $filename = sanitize_file_name(urldecode($_GET['file']));
            $upload_dir = wp_upload_dir();
            $filepath = $upload_dir['basedir'] . '/woohsn-pro/' . $filename;
            
            if (!file_exists($filepath)) {
                wp_die(__('File not found.', 'woohsn-pro'));
            }
            
            // Force download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            
            readfile($filepath);
            
            // Clean up file after download
            unlink($filepath);
            exit;
        }
    }
    
    /**
     * Validate CSV file
     */
    private function validate_csv_file($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload error.', 'woohsn-pro'));
        }
        
        $file_info = pathinfo($file['name']);
        if (strtolower($file_info['extension']) !== 'csv') {
            return new WP_Error('invalid_file_type', __('Only CSV files are allowed.', 'woohsn-pro'));
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            return new WP_Error('file_too_large', __('File size exceeds 5MB limit.', 'woohsn-pro'));
        }
        
        return true;
    }
    
    /**
     * Process CSV import
     */
    private function process_csv_import($filepath) {
        $handle = fopen($filepath, 'r');
        
        if (!$handle) {
            return array(
                'success' => 0,
                'errors' => 1,
                'total' => 0,
                'messages' => array(__('Unable to read CSV file.', 'woohsn-pro'))
            );
        }
        
        $success_count = 0;
        $error_count = 0;
        $total_count = 0;
        $messages = array();
        
        // Skip header row
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            $total_count++;
            
            if (count($row) < 2) {
                $error_count++;
                $messages[] = sprintf(__('Row %d: Invalid format (missing columns).', 'woohsn-pro'), $total_count);
                continue;
            }
            
            $product_id = intval($row[0]);
            $hsn_code = sanitize_text_field($row[1]);
            
            // Validate product exists
            $product = wc_get_product($product_id);
            if (!$product) {
                $error_count++;
                $messages[] = sprintf(__('Row %d: Product ID %d not found.', 'woohsn-pro'), $total_count, $product_id);
                continue;
            }
            
            // Update HSN code
            if (update_post_meta($product_id, 'woohsn_pro_code', $hsn_code)) {
                $success_count++;
            } else {
                $error_count++;
                $messages[] = sprintf(__('Row %d: Failed to update product %d.', 'woohsn-pro'), $total_count, $product_id);
            }
        }
        
        fclose($handle);
        
        return array(
            'success' => $success_count,
            'errors' => $error_count,
            'total' => $total_count,
            'messages' => $messages
        );
    }
    
    /**
     * Export products to CSV
     */
    private function export_products($include_empty = false) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        if (!$include_empty) {
            $args['meta_query'] = array(
                array(
                    'key' => 'woohsn_pro_code',
                    'value' => '',
                    'compare' => '!='
                )
            );
        }
        
        $product_ids = get_posts($args);
        
        if (empty($product_ids)) {
            return new WP_Error('no_products', __('No products found for export.', 'woohsn-pro'));
        }
        
        $upload_dir = wp_upload_dir();
        $woohsn_dir = $upload_dir['basedir'] . '/woohsn-pro';
        $filename = 'export_products_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $woohsn_dir . '/' . $filename;
        
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            return new WP_Error('file_creation_failed', __('Unable to create export file.', 'woohsn-pro'));
        }
        
        // Write header
        fputcsv($handle, array('Product ID', 'Product Name', 'SKU', 'HSN Code', 'GST Rate'));
        
        $count = 0;
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
            $gst_rate = $this->get_gst_rate_for_product($product_id);
            
            fputcsv($handle, array(
                $product_id,
                $product->get_name(),
                $product->get_sku(),
                $hsn_code,
                $gst_rate
            ));
            
            $count++;
        }
        
        fclose($handle);
        
        return array(
            'filename' => $filename,
            'count' => $count
        );
    }
    
    /**
     * Export HSN database to CSV
     */
    private function export_hsn_database() {
        global $wpdb;
        
        $hsn_codes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woohsn_pro_codes ORDER BY hsn_code ASC");
        
        if (empty($hsn_codes)) {
            return new WP_Error('no_hsn_codes', __('No HSN codes found for export.', 'woohsn-pro'));
        }
        
        $upload_dir = wp_upload_dir();
        $woohsn_dir = $upload_dir['basedir'] . '/woohsn-pro';
        $filename = 'export_hsn_database_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $woohsn_dir . '/' . $filename;
        
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            return new WP_Error('file_creation_failed', __('Unable to create export file.', 'woohsn-pro'));
        }
        
        // Write header
        fputcsv($handle, array('HSN Code', 'Description', 'GST Rate'));
        
        foreach ($hsn_codes as $hsn) {
            fputcsv($handle, array(
                $hsn->hsn_code,
                $hsn->description,
                $hsn->gst_rate
            ));
        }
        
        fclose($handle);
        
        return array(
            'filename' => $filename,
            'count' => count($hsn_codes)
        );
    }
    
    /**
     * Process HSN database import
     */
    private function process_hsn_database_import($filepath) {
        global $wpdb;
        
        $handle = fopen($filepath, 'r');
        
        if (!$handle) {
            return array(
                'success' => 0,
                'errors' => 1,
                'total' => 0,
                'messages' => array(__('Unable to read CSV file.', 'woohsn-pro'))
            );
        }
        
        $success_count = 0;
        $error_count = 0;
        $total_count = 0;
        $messages = array();
        
        // Skip header row
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            $total_count++;
            
            if (count($row) < 3) {
                $error_count++;
                $messages[] = sprintf(__('Row %d: Invalid format (missing columns).', 'woohsn-pro'), $total_count);
                continue;
            }
            
            $hsn_code = sanitize_text_field($row[0]);
            $description = sanitize_text_field($row[1]);
            $gst_rate = floatval($row[2]);
            
            // Insert or update HSN code
            $result = $wpdb->replace(
                $wpdb->prefix . 'woohsn_pro_codes',
                array(
                    'hsn_code' => $hsn_code,
                    'description' => $description,
                    'gst_rate' => $gst_rate
                ),
                array('%s', '%s', '%f')
            );
            
            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
                $messages[] = sprintf(__('Row %d: Failed to import HSN code %s.', 'woohsn-pro'), $total_count, $hsn_code);
            }
        }
        
        fclose($handle);
        
        return array(
            'success' => $success_count,
            'errors' => $error_count,
            'total' => $total_count,
            'messages' => $messages
        );
    }
    
    /**
     * Get GST rate for product
     */
    private function get_gst_rate_for_product($product_id) {
        $custom_gst_enabled = get_post_meta($product_id, 'woohsn_pro_enable_custom_gst', true);
        
        if ($custom_gst_enabled === 'yes') {
            return get_post_meta($product_id, 'woohsn_pro_custom_gst_rate', true);
        }
        
        $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
        
        if (empty($hsn_code)) {
            return '';
        }
        
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT gst_rate FROM {$wpdb->prefix}woohsn_pro_codes WHERE hsn_code = %s",
            $hsn_code
        ));
    }
    
    /**
     * Log operation
     */
    private function log_operation($operation_type, $filename, $total, $success, $errors) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'woohsn_pro_logs',
            array(
                'operation_type' => $operation_type,
                'file_name' => $filename,
                'records_processed' => $total,
                'success_count' => $success,
                'error_count' => $errors,
                'user_id' => get_current_user_id()
            ),
            array('%s', '%s', '%d', '%d', '%d', '%d')
        );
    }
}