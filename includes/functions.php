<?php
/**
 * Helper functions for WooHSN
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get HSN code for product
 */
function woohsn_get_product_hsn_code($product_id) {
    return get_post_meta($product_id, 'woohsn_code', true);
}

/**
 * Get GST rate for HSN code
 */
function woohsn_get_gst_rate($hsn_code) {
    global $wpdb;
    
    $cache_key = 'woohsn_gst_rate_' . $hsn_code;
    $gst_rate = get_transient($cache_key);
    
    if ($gst_rate === false) {
        $gst_rate = $wpdb->get_var($wpdb->prepare(
            "SELECT gst_rate FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
            $hsn_code
        ));
        
        $gst_rate = $gst_rate ? floatval($gst_rate) : 0;
        $cache_duration = get_option('woohsn_cache_duration', 3600);
        set_transient($cache_key, $gst_rate, $cache_duration);
    }
    
    return floatval($gst_rate);
}

/**
 * Format HSN code display
 */
function woohsn_format_hsn_display($hsn_code, $format = null) {
    if (empty($hsn_code)) {
        return '';
    }
    
    if (!$format) {
        $format = get_option('woohsn_display_format', 'HSN Code: {code}');
    }
    
    return str_replace('{code}', $hsn_code, $format);
}

/**
 * Get product GST calculation
 */
function woohsn_calculate_product_gst($product_id, $price = null, $quantity = 1) {
    if (!$price) {
        $product = wc_get_product($product_id);
        $price = $product ? $product->get_price() : 0;
    }
    
    $hsn_code = woohsn_get_product_hsn_code($product_id);
    $gst_rate = woohsn_get_gst_rate($hsn_code);
    
    $subtotal = $price * $quantity;
    $gst_amount = ($subtotal * $gst_rate) / 100;
    $total = $subtotal + $gst_amount;
    
    return array(
        'hsn_code' => $hsn_code,
        'gst_rate' => $gst_rate,
        'subtotal' => $subtotal,
        'gst_amount' => $gst_amount,
        'total' => $total
    );
}

/**
 * Check if HSN code exists in database
 */
function woohsn_hsn_code_exists($hsn_code) {
    global $wpdb;
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
        $hsn_code
    ));
    
    return !empty($exists);
}

/**
 * Get HSN code description
 */
function woohsn_get_hsn_description($hsn_code) {
    global $wpdb;
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT description FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
        $hsn_code
    ));
}

/**
 * Validate HSN code format
 */
function woohsn_validate_hsn_code($hsn_code) {
    // HSN codes are typically 4-8 digit numbers
    return preg_match('/^[0-9]{4,8}$/', $hsn_code);
}

/**
 * Log plugin activity
 */
function woohsn_log_activity($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[WooHSN] ' . $message);
    }
}

/**
 * Get plugin version
 */
function woohsn_get_version() {
    return WOOHSN_VERSION;
}

/**
 * Check if WooCommerce is active
 */
function woohsn_is_woocommerce_active() {
    return class_exists('WooCommerce');
}

/**
 * Get all GST rates
 */
function woohsn_get_all_gst_rates() {
    global $wpdb;
    
    $rates = $wpdb->get_col("SELECT DISTINCT gst_rate FROM {$wpdb->prefix}woohsn_codes ORDER BY gst_rate ASC");
    
    return array_map('floatval', $rates);
}