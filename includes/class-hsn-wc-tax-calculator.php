<?php
/**
 * Tax Calculator functionality for WooHSN Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Pro_Tax_Calculator {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_gst_fees'));
        add_filter('woocommerce_product_get_tax_class', array($this, 'set_product_tax_class'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_hsn_to_order_item'), 10, 4);
        add_action('wp_ajax_woohsn_pro_calculate_tax', array($this, 'ajax_calculate_tax'));
        add_action('wp_ajax_nopriv_woohsn_pro_calculate_tax', array($this, 'ajax_calculate_tax'));
        add_filter('woocommerce_order_item_display_meta_key', array($this, 'display_hsn_meta_key'), 10, 3);
        add_filter('woocommerce_order_item_display_meta_value', array($this, 'display_hsn_meta_value'), 10, 3);
    }
    
    /**
     * Calculate GST fees in cart
     */
    public function calculate_gst_fees() {
        if (!get_option('woohsn_pro_enable_tax_calculation', 'yes') === 'yes') {
            return;
        }
        
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }
        
        $gst_breakdown = array();
        $total_gst = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $line_total = $cart_item['line_total'];
            
            $gst_rate = $this->get_product_gst_rate($product_id);
            
            if ($gst_rate > 0) {
                $gst_amount = ($line_total * $gst_rate) / 100;
                $total_gst += $gst_amount;
                
                $gst_breakdown[] = array(
                    'rate' => $gst_rate,
                    'amount' => $gst_amount,
                    'product_id' => $product_id
                );
            }
        }
        
        if ($total_gst > 0) {
            $display_mode = get_option('woohsn_pro_gst_display_mode', 'consolidated');
            
            if ($display_mode === 'consolidated') {
                $cart->add_fee(__('GST', 'woohsn-pro'), $total_gst);
            } else {
                // Break down by GST rate
                $rate_totals = array();
                foreach ($gst_breakdown as $item) {
                    $rate = $item['rate'];
                    if (!isset($rate_totals[$rate])) {
                        $rate_totals[$rate] = 0;
                    }
                    $rate_totals[$rate] += $item['amount'];
                }
                
                foreach ($rate_totals as $rate => $amount) {
                    $cart->add_fee(sprintf(__('GST (%s%%)', 'woohsn-pro'), $rate), $amount);
                }
            }
        }
    }
    
    /**
     * Set product tax class based on HSN code
     */
    public function set_product_tax_class($tax_class, $product) {
        if (!get_option('woohsn_pro_enable_tax_calculation', 'yes') === 'yes') {
            return $tax_class;
        }
        
        $product_id = $product->get_id();
        $gst_rate = $this->get_product_gst_rate($product_id);
        
        if ($gst_rate > 0) {
            // Map GST rates to WooCommerce tax classes
            $tax_class_mapping = get_option('woohsn_pro_tax_class_mapping', array());
            
            if (isset($tax_class_mapping[$gst_rate])) {
                return $tax_class_mapping[$gst_rate];
            }
            
            // Default mapping
            switch ($gst_rate) {
                case 0:
                    return 'zero-rate';
                case 5:
                    return 'reduced-rate';
                case 12:
                    return 'standard-rate';
                case 18:
                    return 'high-rate';
                case 28:
                    return 'luxury-rate';
                default:
                    return $tax_class;
            }
        }
        
        return $tax_class;
    }
    
    /**
     * Add HSN code to order item meta
     */
    public function add_hsn_to_order_item($item, $cart_item_key, $values, $order) {
        $product_id = $values['product_id'];
        $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
        
        if (!empty($hsn_code)) {
            $item->add_meta_data(__('HSN Code', 'woohsn-pro'), $hsn_code);
            
            $gst_rate = $this->get_product_gst_rate($product_id);
            if ($gst_rate > 0) {
                $item->add_meta_data(__('GST Rate', 'woohsn-pro'), $gst_rate . '%');
                
                // Calculate GST amount for this item
                $line_total = $item->get_total();
                $gst_amount = ($line_total * $gst_rate) / 100;
                $item->add_meta_data(__('GST Amount', 'woohsn-pro'), wc_price($gst_amount));
            }
        }
    }
    
    /**
     * AJAX calculate tax for preview
     */
    public function ajax_calculate_tax() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $price = floatval($_POST['price']);
        
        $gst_rate = $this->get_product_gst_rate($product_id);
        $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
        
        $subtotal = $price * $quantity;
        $gst_amount = ($subtotal * $gst_rate) / 100;
        $total = $subtotal + $gst_amount;
        
        $response = array(
            'hsn_code' => $hsn_code,
            'gst_rate' => $gst_rate,
            'subtotal' => wc_price($subtotal),
            'gst_amount' => wc_price($gst_amount),
            'total' => wc_price($total),
            'breakdown' => array(
                'base_price' => wc_price($price),
                'quantity' => $quantity,
                'subtotal_raw' => $subtotal,
                'gst_amount_raw' => $gst_amount,
                'total_raw' => $total
            )
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Display HSN meta key in orders
     */
    public function display_hsn_meta_key($display_key, $meta, $item) {
        if ($meta->key === 'hsn_code') {
            return __('HSN Code', 'woohsn-pro');
        }
        if ($meta->key === 'gst_rate') {
            return __('GST Rate', 'woohsn-pro');
        }
        if ($meta->key === 'gst_amount') {
            return __('GST Amount', 'woohsn-pro');
        }
        return $display_key;
    }
    
    /**
     * Display HSN meta value in orders
     */
    public function display_hsn_meta_value($display_value, $meta, $item) {
        if ($meta->key === 'hsn_code') {
            return '<span class="woohsn-pro-order-hsn">' . esc_html($display_value) . '</span>';
        }
        if ($meta->key === 'gst_rate') {
            return '<span class="woohsn-pro-order-gst-rate">' . esc_html($display_value) . '</span>';
        }
        if ($meta->key === 'gst_amount') {
            return '<span class="woohsn-pro-order-gst-amount">' . $display_value . '</span>';
        }
        return $display_value;
    }
    
    /**
     * Get product GST rate
     */
    public function get_product_gst_rate($product_id) {
        // Check if custom GST rate is enabled
        $custom_gst_enabled = get_post_meta($product_id, 'woohsn_pro_enable_custom_gst', true);
        
        if ($custom_gst_enabled === 'yes') {
            $custom_rate = get_post_meta($product_id, 'woohsn_pro_custom_gst_rate', true);
            return floatval($custom_rate);
        }
        
        // Get GST rate from HSN code
        $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
        
        if (empty($hsn_code)) {
            return 0;
        }
        
        global $wpdb;
        
        $cache_key = 'woohsn_pro_gst_rate_' . $hsn_code;
        $gst_rate = get_transient($cache_key);
        
        if ($gst_rate === false) {
            $gst_rate = $wpdb->get_var($wpdb->prepare(
                "SELECT gst_rate FROM {$wpdb->prefix}woohsn_pro_codes WHERE hsn_code = %s",
                $hsn_code
            ));
            
            $gst_rate = $gst_rate ? floatval($gst_rate) : 0;
            
            $cache_duration = get_option('woohsn_pro_cache_duration', 3600);
            set_transient($cache_key, $gst_rate, $cache_duration);
        }
        
        return floatval($gst_rate);
    }
    
    /**
     * Calculate invoice totals with GST breakdown
     */
    public function calculate_invoice_totals($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        $totals = array(
            'subtotal' => 0,
            'gst_breakdown' => array(),
            'total_gst' => 0,
            'grand_total' => 0
        );
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $line_total = $item->get_total();
            $quantity = $item->get_quantity();
            
            $totals['subtotal'] += $line_total;
            
            $gst_rate = $this->get_product_gst_rate($product_id);
            $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
            
            if ($gst_rate > 0) {
                $gst_amount = ($line_total * $gst_rate) / 100;
                $totals['total_gst'] += $gst_amount;
                
                if (!isset($totals['gst_breakdown'][$gst_rate])) {
                    $totals['gst_breakdown'][$gst_rate] = array(
                        'rate' => $gst_rate,
                        'taxable_amount' => 0,
                        'gst_amount' => 0,
                        'items' => array()
                    );
                }
                
                $totals['gst_breakdown'][$gst_rate]['taxable_amount'] += $line_total;
                $totals['gst_breakdown'][$gst_rate]['gst_amount'] += $gst_amount;
                $totals['gst_breakdown'][$gst_rate]['items'][] = array(
                    'name' => $item->get_name(),
                    'hsn_code' => $hsn_code,
                    'quantity' => $quantity,
                    'line_total' => $line_total,
                    'gst_amount' => $gst_amount
                );
            }
        }
        
        $totals['grand_total'] = $totals['subtotal'] + $totals['total_gst'];
        
        return $totals;
    }
    
    /**
     * Generate GST report data
     */
    public function generate_gst_report($start_date, $end_date) {
        global $wpdb;
        
        $start_date = sanitize_text_field($start_date);
        $end_date = sanitize_text_field($end_date);
        
        // Get orders within date range
        $orders = wc_get_orders(array(
            'status' => array('completed', 'processing'),
            'date_created' => $start_date . '...' . $end_date,
            'limit' => -1
        ));
        
        $report_data = array(
            'period' => array(
                'start' => $start_date,
                'end' => $end_date
            ),
            'summary' => array(
                'total_orders' => count($orders),
                'total_sales' => 0,
                'total_gst' => 0,
                'gst_breakdown' => array()
            ),
            'hsn_wise' => array(),
            'monthly_breakdown' => array()
        );
        
        foreach ($orders as $order) {
            $order_totals = $this->calculate_invoice_totals($order->get_id());
            
            $report_data['summary']['total_sales'] += $order_totals['subtotal'];
            $report_data['summary']['total_gst'] += $order_totals['total_gst'];
            
            // Process GST breakdown
            foreach ($order_totals['gst_breakdown'] as $rate_data) {
                $rate = $rate_data['rate'];
                
                if (!isset($report_data['summary']['gst_breakdown'][$rate])) {
                    $report_data['summary']['gst_breakdown'][$rate] = array(
                        'rate' => $rate,
                        'taxable_amount' => 0,
                        'gst_amount' => 0
                    );
                }
                
                $report_data['summary']['gst_breakdown'][$rate]['taxable_amount'] += $rate_data['taxable_amount'];
                $report_data['summary']['gst_breakdown'][$rate]['gst_amount'] += $rate_data['gst_amount'];
                
                // Process HSN-wise data
                foreach ($rate_data['items'] as $item) {
                    $hsn_code = $item['hsn_code'];
                    
                    if (!isset($report_data['hsn_wise'][$hsn_code])) {
                        $report_data['hsn_wise'][$hsn_code] = array(
                            'hsn_code' => $hsn_code,
                            'total_sales' => 0,
                            'total_gst' => 0,
                            'gst_rate' => $rate
                        );
                    }
                    
                    $report_data['hsn_wise'][$hsn_code]['total_sales'] += $item['line_total'];
                    $report_data['hsn_wise'][$hsn_code]['total_gst'] += $item['gst_amount'];
                }
            }
            
            // Monthly breakdown
            $order_month = $order->get_date_created()->format('Y-m');
            
            if (!isset($report_data['monthly_breakdown'][$order_month])) {
                $report_data['monthly_breakdown'][$order_month] = array(
                    'month' => $order_month,
                    'sales' => 0,
                    'gst' => 0
                );
            }
            
            $report_data['monthly_breakdown'][$order_month]['sales'] += $order_totals['subtotal'];
            $report_data['monthly_breakdown'][$order_month]['gst'] += $order_totals['total_gst'];
        }
        
        return $report_data;
    }
}