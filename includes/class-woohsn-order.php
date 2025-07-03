<?php
/**
 * Order functionality for WooHSN with HPOS compatibility
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Order {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Order meta box hooks (works for both HPOS and legacy)
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));
        add_action('save_post', array($this, 'save_order_meta_box'));
        
        // HPOS specific hooks
        if (WooHSN_HPOS_Compatibility::is_hpos_enabled()) {
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_hsn_summary'));
        } else {
            // Legacy hooks
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_hsn_summary'));
        }
        
        // Order calculation hooks
        add_action('woocommerce_checkout_order_processed', array($this, 'process_order_hsn_data'), 10, 1);
        add_action('woocommerce_order_status_changed', array($this, 'update_order_hsn_on_status_change'), 10, 3);
        
        // Admin order columns
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_order_columns'), 20);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_order_column_content'), 20, 2);
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_column_content_legacy'), 20, 2);
    }
    
    /**
     * Add meta boxes for orders
     */
    public function add_order_meta_boxes() {
        $screen = WooHSN_HPOS_Compatibility::is_hpos_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
        
        add_meta_box(
            'woohsn-order-hsn',
            __('HSN Code Summary', 'woohsn'),
            array($this, 'order_hsn_meta_box_callback'),
            $screen,
            'side',
            'default'
        );
    }
    
    /**
     * Order HSN meta box callback
     */
    public function order_hsn_meta_box_callback($post_or_order) {
        // Handle both HPOS and legacy
        $order = ($post_or_order instanceof WP_Post) ? wc_get_order($post_or_order->ID) : $post_or_order;
        
        if (!$order) {
            return;
        }
        
        $order_id = $order->get_id();
        
        // Get HSN summary
        $hsn_summary = $this->get_order_hsn_summary($order);
        
        if (empty($hsn_summary)) {
            echo '<p>' . esc_html__('No HSN codes found for this order.', 'woohsn') . '</p>';
            return;
        }
        
        echo '<div class="woohsn-order-summary">';
        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('HSN Code', 'woohsn') . '</th>';
        echo '<th>' . esc_html__('Taxable Amount', 'woohsn') . '</th>';
        echo '<th>' . esc_html__('GST Rate', 'woohsn') . '</th>';
        echo '<th>' . esc_html__('GST Amount', 'woohsn') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($hsn_summary as $hsn_code => $data) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($hsn_code) . '</strong></td>';
            echo '<td>' . wc_price($data['taxable_amount']) . '</td>';
            echo '<td>' . esc_html($data['gst_rate']) . '%</td>';
            echo '<td>' . wc_price($data['gst_amount']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        // Store HSN summary in order meta
        WooHSN_HPOS_Compatibility::update_order_meta($order_id, '_woohsn_summary', $hsn_summary);
    }
    
    /**
     * Save order meta box data
     */
    public function save_order_meta_box($post_id) {
        // Only process for orders
        if (!WooHSN_HPOS_Compatibility::is_wc_order($post_id)) {
            return;
        }
        
        $order = WooHSN_HPOS_Compatibility::get_order($post_id);
        if (!$order) {
            return;
        }
        
        // Recalculate HSN data
        $this->calculate_and_store_order_hsn_data($order);
    }
    
    /**
     * Display HSN summary in order admin
     */
    public function display_order_hsn_summary($order) {
        if (!is_admin()) {
            return;
        }
        
        $order_id = $order->get_id();
        $hsn_summary = WooHSN_HPOS_Compatibility::get_order_meta($order_id, '_woohsn_summary', true);
        
        if (empty($hsn_summary)) {
            return;
        }
        
        echo '<div class="woohsn-admin-summary" style="margin-top: 20px;">';
        echo '<h4>' . esc_html__('HSN Code Summary', 'woohsn') . '</h4>';
        echo '<table class="wc-order-totals" style="width: 100%;">';
        
        foreach ($hsn_summary as $hsn_code => $data) {
            echo '<tr>';
            echo '<td class="label">' . esc_html__('HSN', 'woohsn') . ' ' . esc_html($hsn_code) . ':</td>';
            echo '<td class="total">' . wc_price($data['gst_amount']) . ' <small>(' . esc_html($data['gst_rate']) . '% GST)</small></td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Process HSN data when order is created
     */
    public function process_order_hsn_data($order_id) {
        $order = WooHSN_HPOS_Compatibility::get_order($order_id);
        if (!$order) {
            return;
        }
        
        $this->calculate_and_store_order_hsn_data($order);
    }
    
    /**
     * Update HSN data when order status changes
     */
    public function update_order_hsn_on_status_change($order_id, $from_status, $to_status) {
        // Only recalculate for specific status changes
        $recalculate_statuses = array('processing', 'completed');
        
        if (in_array($to_status, $recalculate_statuses)) {
            $order = WooHSN_HPOS_Compatibility::get_order($order_id);
            if ($order) {
                $this->calculate_and_store_order_hsn_data($order);
            }
        }
    }
    
    /**
     * Calculate and store HSN data for an order
     */
    public function calculate_and_store_order_hsn_data($order) {
        $order_id = $order->get_id();
        $hsn_summary = $this->get_order_hsn_summary($order);
        
        // Store the summary
        WooHSN_HPOS_Compatibility::update_order_meta($order_id, '_woohsn_summary', $hsn_summary);
        
        // Store total GST amount
        $total_gst = 0;
        foreach ($hsn_summary as $data) {
            $total_gst += $data['gst_amount'];
        }
        
        WooHSN_HPOS_Compatibility::update_order_meta($order_id, '_woohsn_total_gst', $total_gst);
        
        // Store calculation timestamp
        WooHSN_HPOS_Compatibility::update_order_meta($order_id, '_woohsn_calculated_at', current_time('timestamp'));
    }
    
    /**
     * Get HSN summary for an order
     */
    public function get_order_hsn_summary($order) {
        $hsn_summary = array();
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $hsn_code = get_post_meta($product_id, 'woohsn_code', true);
            
            if (empty($hsn_code)) {
                continue;
            }
            
            $line_total = $item->get_total();
            $gst_rate = $this->get_product_gst_rate($product_id, $hsn_code);
            $gst_amount = ($line_total * $gst_rate) / 100;
            
            if (!isset($hsn_summary[$hsn_code])) {
                $hsn_summary[$hsn_code] = array(
                    'hsn_code' => $hsn_code,
                    'taxable_amount' => 0,
                    'gst_rate' => $gst_rate,
                    'gst_amount' => 0,
                    'items' => array()
                );
            }
            
            $hsn_summary[$hsn_code]['taxable_amount'] += $line_total;
            $hsn_summary[$hsn_code]['gst_amount'] += $gst_amount;
            $hsn_summary[$hsn_code]['items'][] = array(
                'product_id' => $product_id,
                'line_total' => $line_total,
                'gst_amount' => $gst_amount
            );
        }
        
        return $hsn_summary;
    }
    
    /**
     * Get GST rate for a product
     */
    private function get_product_gst_rate($product_id, $hsn_code) {
        // Check for custom GST rate
        $enable_custom_gst = get_post_meta($product_id, 'woohsn_enable_custom_gst', true);
        if ($enable_custom_gst === 'yes') {
            $custom_rate = get_post_meta($product_id, 'woohsn_custom_gst_rate', true);
            if ($custom_rate !== '') {
                return floatval($custom_rate);
            }
        }
        
        // Get rate from HSN database
        global $wpdb;
        $gst_rate = $wpdb->get_var($wpdb->prepare(
            "SELECT gst_rate FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
            $hsn_code
        ));
        
        return $gst_rate ? floatval($gst_rate) : 0;
    }
    
    /**
     * Add columns to orders list
     */
    public function add_order_columns($columns) {
        // Add HSN column after order total
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'order_total') {
                $new_columns['woohsn_gst'] = __('GST Amount', 'woohsn');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display content for order column (HPOS)
     */
    public function display_order_column_content($column, $order) {
        if ($column === 'woohsn_gst') {
            $order_id = $order->get_id();
            $total_gst = WooHSN_HPOS_Compatibility::get_order_meta($order_id, '_woohsn_total_gst', true);
            
            if ($total_gst) {
                echo wc_price($total_gst);
            } else {
                echo '—';
            }
        }
    }
    
    /**
     * Display content for order column (Legacy)
     */
    public function display_order_column_content_legacy($column, $post_id) {
        if ($column === 'woohsn_gst') {
            $total_gst = WooHSN_HPOS_Compatibility::get_order_meta($post_id, '_woohsn_total_gst', true);
            
            if ($total_gst) {
                echo wc_price($total_gst);
            } else {
                echo '—';
            }
        }
    }
}