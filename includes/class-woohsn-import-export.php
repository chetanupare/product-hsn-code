<?php
/**
 * Import/Export functionality for WooHSN
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Import_Export {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_woohsn_import_csv', array($this, 'ajax_import_csv'));
        add_action('wp_ajax_woohsn_export_csv', array($this, 'ajax_export_csv'));
    }
    
    /**
     * AJAX import CSV
     */
    public function ajax_import_csv() {
        check_ajax_referer('woohsn_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn'));
        }
        
        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded.', 'woohsn'));
        }
        
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error.', 'woohsn'));
        }
        
        $csv_data = array_map('str_getcsv', file($file['tmp_name']));
        $headers = array_shift($csv_data);
        
        $success_count = 0;
        $error_count = 0;
        
        global $wpdb;
        
        foreach ($csv_data as $row) {
            if (count($row) >= 3) {
                $hsn_code = sanitize_text_field($row[0]);
                $description = sanitize_textarea_field($row[1]);
                $gst_rate = floatval($row[2]);
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'woohsn_codes',
                    array(
                        'hsn_code' => $hsn_code,
                        'description' => $description,
                        'gst_rate' => $gst_rate
                    ),
                    array('%s', '%s', '%f')
                );
                
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        wp_send_json_success(array(
            'success_count' => $success_count,
            'error_count' => $error_count
        ));
    }
    
    /**
     * AJAX export CSV
     */
    public function ajax_export_csv() {
        check_ajax_referer('woohsn_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'woohsn'));
        }
        
        global $wpdb;
        
        $hsn_codes = $wpdb->get_results("SELECT hsn_code, description, gst_rate FROM {$wpdb->prefix}woohsn_codes ORDER BY hsn_code ASC");
        
        $filename = 'woohsn_codes_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array('HSN Code', 'Description', 'GST Rate'));
        
        // Data
        foreach ($hsn_codes as $hsn_code) {
            fputcsv($output, array(
                $hsn_code->hsn_code,
                $hsn_code->description,
                $hsn_code->gst_rate
            ));
        }
        
        fclose($output);
        exit;
    }
}