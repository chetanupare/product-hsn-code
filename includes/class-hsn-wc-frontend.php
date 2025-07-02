<?php
/**
 * Frontend functionality for WooHSN Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Pro_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('woocommerce_single_product_summary', array($this, 'display_hsn_code'), 25);
        add_action('woocommerce_after_shop_loop_item_title', array($this, 'display_hsn_code_shop'), 15);
        add_filter('woocommerce_cart_item_name', array($this, 'add_hsn_to_cart'), 10, 3);
        add_action('woocommerce_order_item_meta_end', array($this, 'display_hsn_in_order'), 10, 4);
        add_shortcode('woohsn_code', array($this, 'hsn_code_shortcode'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        if (is_product() || is_shop() || is_product_category() || is_cart() || is_checkout()) {
            wp_enqueue_style('woohsn-pro-frontend-css', WOOHSN_PRO_PLUGIN_URL . 'assets/css/frontend.css', array(), WOOHSN_PRO_VERSION);
            wp_enqueue_script('woohsn-pro-frontend-js', WOOHSN_PRO_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), WOOHSN_PRO_VERSION, true);
        }
    }
    
    /**
     * Display HSN code on single product page
     */
    public function display_hsn_code() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $hsn_code = get_post_meta($product->get_id(), 'woohsn_pro_code', true);
        
        if (empty($hsn_code)) {
            return;
        }
        
        $display_format = get_option('woohsn_pro_display_format', 'HSN Code: {code}');
        $show_gst_rate = get_option('woohsn_pro_show_gst_rate', 'yes');
        
        // Get GST rate if enabled
        $gst_rate = '';
        if ($show_gst_rate === 'yes') {
            $gst_rate = $this->get_gst_rate_for_hsn($hsn_code);
        }
        
        $output = str_replace('{code}', $hsn_code, $display_format);
        
        if (!empty($gst_rate) && $show_gst_rate === 'yes') {
            $output .= ' <span class="woohsn-pro-gst-rate">(GST: ' . $gst_rate . '%)</span>';
        }
        
        $this->render_hsn_display($output, 'single-product');
    }
    
    /**
     * Display HSN code on shop page
     */
    public function display_hsn_code_shop() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $display_in_shop = get_option('woohsn_pro_display_in_shop', 'no');
        
        if ($display_in_shop !== 'yes') {
            return;
        }
        
        $hsn_code = get_post_meta($product->get_id(), 'woohsn_pro_code', true);
        
        if (empty($hsn_code)) {
            return;
        }
        
        $output = 'HSN: ' . $hsn_code;
        $this->render_hsn_display($output, 'shop-loop');
    }
    
    /**
     * Add HSN code to cart item name
     */
    public function add_hsn_to_cart($product_name, $cart_item, $cart_item_key) {
        $show_in_cart = get_option('woohsn_pro_display_in_cart', 'no');
        
        if ($show_in_cart !== 'yes') {
            return $product_name;
        }
        
        $product_id = $cart_item['product_id'];
        $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
        
        if (!empty($hsn_code)) {
            $product_name .= '<br><small class="woohsn-pro-cart-hsn">HSN: ' . esc_html($hsn_code) . '</small>';
        }
        
        return $product_name;
    }
    
    /**
     * Display HSN code in order details
     */
    public function display_hsn_in_order($item_id, $item, $order, $plain_text) {
        $show_in_order = get_option('woohsn_pro_display_in_order', 'yes');
        
        if ($show_in_order !== 'yes') {
            return;
        }
        
        $product_id = $item->get_product_id();
        $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
        
        if (!empty($hsn_code)) {
            if ($plain_text) {
                echo "\nHSN Code: " . $hsn_code;
            } else {
                echo '<div class="woohsn-pro-order-hsn"><strong>HSN Code:</strong> ' . esc_html($hsn_code) . '</div>';
            }
        }
    }
    
    /**
     * HSN code shortcode
     */
    public function hsn_code_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID(),
            'format' => 'HSN: {code}',
            'show_gst' => 'no'
        ), $atts, 'woohsn_code');
        
        $product_id = intval($atts['product_id']);
        $hsn_code = get_post_meta($product_id, 'woohsn_pro_code', true);
        
        if (empty($hsn_code)) {
            return '';
        }
        
        $output = str_replace('{code}', $hsn_code, $atts['format']);
        
        if ($atts['show_gst'] === 'yes') {
            $gst_rate = $this->get_gst_rate_for_hsn($hsn_code);
            if (!empty($gst_rate)) {
                $output .= ' (GST: ' . $gst_rate . '%)';
            }
        }
        
        return '<span class="woohsn-pro-shortcode">' . esc_html($output) . '</span>';
    }
    
    /**
     * Get GST rate for HSN code
     */
    private function get_gst_rate_for_hsn($hsn_code) {
        global $wpdb;
        
        $cache_key = 'woohsn_pro_gst_rate_' . $hsn_code;
        $gst_rate = get_transient($cache_key);
        
        if ($gst_rate === false) {
            $gst_rate = $wpdb->get_var($wpdb->prepare(
                "SELECT gst_rate FROM {$wpdb->prefix}woohsn_pro_codes WHERE hsn_code = %s",
                $hsn_code
            ));
            
            $cache_duration = get_option('woohsn_pro_cache_duration', 3600);
            set_transient($cache_key, $gst_rate, $cache_duration);
        }
        
        return $gst_rate;
    }
    
    /**
     * Render HSN display with custom styling
     */
    private function render_hsn_display($content, $context = 'single-product') {
        $color = get_option('woohsn_pro_color', '#333333');
        $font_size = get_option('woohsn_pro_font_size', '14');
        $font_weight = get_option('woohsn_pro_font_weight', 'normal');
        $background_color = get_option('woohsn_pro_background_color', '#f8f9fa');
        $border_color = get_option('woohsn_pro_border_color', '#dee2e6');
        
        $styles = array(
            'color: ' . esc_attr($color),
            'font-size: ' . esc_attr($font_size) . 'px',
            'font-weight: ' . esc_attr($font_weight),
            'background-color: ' . esc_attr($background_color),
            'border: 1px solid ' . esc_attr($border_color),
            'padding: 8px 12px',
            'margin: 10px 0',
            'border-radius: 4px',
            'display: inline-block'
        );
        
        $style_string = implode('; ', $styles);
        
        echo '<div class="woohsn-pro-display woohsn-pro-' . esc_attr($context) . '" style="' . $style_string . '">';
        echo wp_kses_post($content);
        echo '</div>';
    }
}