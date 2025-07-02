<?php
/**
 * Tax calculation functionality for WooHSN
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Tax_Calculator {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_cart_tax_totals', array($this, 'add_hsn_to_tax_totals'), 10, 2);
        add_action('wp_ajax_woohsn_calculate_gst', array($this, 'ajax_calculate_gst'));
    }
    
    /**
     * Add HSN information to tax totals
     */
    public function add_hsn_to_tax_totals($tax_totals, $cart) {
        if (!get_option('woohsn_enable_tax_calculation', 'yes')) {
            return $tax_totals;
        }
        
        return $tax_totals;
    }
    
    /**
     * AJAX calculate GST for product
     */
    public function ajax_calculate_gst() {
        check_ajax_referer('woohsn_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        
        $calculation = woohsn_calculate_product_gst($product_id, $price, $quantity);
        
        wp_send_json_success($calculation);
    }
    
    /**
     * Calculate order totals with HSN breakdown
     */
    public function calculate_order_totals($order) {
        $order_totals = array(
            'subtotal' => 0,
            'total_gst' => 0,
            'gst_breakdown' => array(),
            'hsn_wise' => array()
        );
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $hsn_code = get_post_meta($product_id, 'woohsn_code', true);
            
            if (!empty($hsn_code)) {
                $line_total = $item->get_total();
                $gst_rate = woohsn_get_gst_rate($hsn_code);
                $gst_amount = ($line_total * $gst_rate) / 100;
                
                $order_totals['subtotal'] += $line_total;
                $order_totals['total_gst'] += $gst_amount;
                
                // Group by GST rate
                if (!isset($order_totals['gst_breakdown'][$gst_rate])) {
                    $order_totals['gst_breakdown'][$gst_rate] = array(
                        'rate' => $gst_rate,
                        'taxable_amount' => 0,
                        'gst_amount' => 0,
                        'items' => array()
                    );
                }
                
                $order_totals['gst_breakdown'][$gst_rate]['taxable_amount'] += $line_total;
                $order_totals['gst_breakdown'][$gst_rate]['gst_amount'] += $gst_amount;
                $order_totals['gst_breakdown'][$gst_rate]['items'][] = array(
                    'hsn_code' => $hsn_code,
                    'line_total' => $line_total,
                    'gst_amount' => $gst_amount
                );
            }
        }
        
        return $order_totals;
    }
}