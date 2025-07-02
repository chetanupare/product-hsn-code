<?php
/**
 * Admin functionality for HSN for WooCommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class HSN_WC_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('wp_ajax_woohsn_pro_search_hsn', array($this, 'ajax_search_hsn'));
        add_action('wp_ajax_woohsn_pro_bulk_assign', array($this, 'ajax_bulk_assign'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_filter('plugin_action_links_' . WOOHSN_PRO_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WooHSN Pro', 'woohsn-pro'),
            __('WooHSN Pro', 'woohsn-pro'),
            'manage_options',
            'woohsn-pro',
            array($this, 'admin_page'),
            'dashicons-list-view',
            30
        );
        
        add_submenu_page(
            'woohsn-pro',
            __('Dashboard', 'woohsn-pro'),
            __('Dashboard', 'woohsn-pro'),
            'manage_options',
            'woohsn-pro',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'woohsn-pro',
            __('HSN Database', 'woohsn-pro'),
            __('HSN Database', 'woohsn-pro'),
            'manage_options',
            'woohsn-pro-database',
            array($this, 'database_page')
        );
        
        add_submenu_page(
            'woohsn-pro',
            __('Bulk Operations', 'woohsn-pro'),
            __('Bulk Operations', 'woohsn-pro'),
            'manage_options',
            'woohsn-pro-bulk',
            array($this, 'bulk_operations_page')
        );
        
        add_submenu_page(
            'woohsn-pro',
            __('Settings', 'woohsn-pro'),
            __('Settings', 'woohsn-pro'),
            'manage_options',
            'woohsn-pro-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'woohsn-pro',
            __('Reports', 'woohsn-pro'),
            __('Reports', 'woohsn-pro'),
            'manage_options',
            'woohsn-pro-reports',
            array($this, 'reports_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'woohsn-pro') !== false) {
            wp_enqueue_style('woohsn-pro-admin-css', WOOHSN_PRO_PLUGIN_URL . 'assets/css/admin.css', array(), WOOHSN_PRO_VERSION);
            wp_enqueue_script('woohsn-pro-admin-js', WOOHSN_PRO_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-color-picker'), WOOHSN_PRO_VERSION, true);
            
            wp_localize_script('woohsn-pro-admin-js', 'woohsn_pro_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('woohsn_pro_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this HSN code?', 'woohsn-pro'),
                    'bulk_assign_success' => __('HSN codes assigned successfully!', 'woohsn-pro'),
                    'bulk_assign_error' => __('Error assigning HSN codes. Please try again.', 'woohsn-pro'),
                )
            ));
            
            wp_enqueue_style('wp-color-picker');
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Display settings
        register_setting('woohsn_pro_display', 'woohsn_pro_display_position');
        register_setting('woohsn_pro_display', 'woohsn_pro_display_format');
        register_setting('woohsn_pro_display', 'woohsn_pro_show_gst_rate');
        
        // Style settings
        register_setting('woohsn_pro_style', 'woohsn_pro_color');
        register_setting('woohsn_pro_style', 'woohsn_pro_font_size');
        register_setting('woohsn_pro_style', 'woohsn_pro_font_weight');
        register_setting('woohsn_pro_style', 'woohsn_pro_background_color');
        register_setting('woohsn_pro_style', 'woohsn_pro_border_color');
        
        // Advanced settings
        register_setting('woohsn_pro_advanced', 'woohsn_pro_enable_tax_calculation');
        register_setting('woohsn_pro_advanced', 'woohsn_pro_cache_duration');
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'woohsn_pro_overview',
            __('WooHSN Pro Overview', 'woohsn-pro'),
            array($this, 'dashboard_widget_overview')
        );
    }
    
    /**
     * Dashboard widget overview
     */
    public function dashboard_widget_overview() {
        global $wpdb;
        
        $total_products = wp_count_posts('product')->publish;
        $products_with_hsn = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'woohsn_pro_code' AND meta_value != ''");
        $total_hsn_codes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_pro_codes");
        $completion_rate = $total_products > 0 ? round(($products_with_hsn / $total_products) * 100, 2) : 0;
        
        ?>
        <div class="woohsn-pro-dashboard-widget">
            <div class="woohsn-pro-stats">
                <div class="stat-item">
                    <h3><?php echo $total_products; ?></h3>
                    <p><?php _e('Total Products', 'woohsn-pro'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $products_with_hsn; ?></h3>
                    <p><?php _e('Products with HSN', 'woohsn-pro'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $total_hsn_codes; ?></h3>
                    <p><?php _e('HSN Codes in Database', 'woohsn-pro'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $completion_rate; ?>%</h3>
                    <p><?php _e('Completion Rate', 'woohsn-pro'); ?></p>
                </div>
            </div>
            <div class="woohsn-pro-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=woohsn-pro-bulk'); ?>" class="button button-primary">
                    <?php _e('Bulk Assign HSN Codes', 'woohsn-pro'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=woohsn-pro-database'); ?>" class="button">
                    <?php _e('Manage HSN Database', 'woohsn-pro'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        include WOOHSN_PRO_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    /**
     * Database page
     */
    public function database_page() {
        include WOOHSN_PRO_PLUGIN_DIR . 'templates/admin-database.php';
    }
    
    /**
     * Bulk operations page
     */
    public function bulk_operations_page() {
        include WOOHSN_PRO_PLUGIN_DIR . 'templates/admin-bulk-operations.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include WOOHSN_PRO_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Reports page
     */
    public function reports_page() {
        include WOOHSN_PRO_PLUGIN_DIR . 'templates/admin-reports.php';
    }
    
    /**
     * AJAX search HSN codes
     */
    public function ajax_search_hsn() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search_term']);
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT hsn_code, description, gst_rate FROM {$wpdb->prefix}woohsn_pro_codes 
             WHERE hsn_code LIKE %s OR description LIKE %s 
             ORDER BY hsn_code ASC LIMIT 20",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        ));
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX bulk assign HSN codes
     */
    public function ajax_bulk_assign() {
        check_ajax_referer('woohsn_pro_nonce', 'nonce');
        
        $product_ids = array_map('intval', $_POST['product_ids']);
        $hsn_code = sanitize_text_field($_POST['hsn_code']);
        
        $success_count = 0;
        foreach ($product_ids as $product_id) {
            if (update_post_meta($product_id, 'woohsn_pro_code', $hsn_code)) {
                $success_count++;
            }
        }
        
        wp_send_json_success(array('success_count' => $success_count));
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['woohsn_pro_message'])) {
            $message = sanitize_text_field($_GET['woohsn_pro_message']);
            $type = isset($_GET['woohsn_pro_type']) ? sanitize_text_field($_GET['woohsn_pro_type']) : 'success';
            
            ?>
            <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=woohsn-pro-settings') . '">' . __('Settings', 'woohsn-pro') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}