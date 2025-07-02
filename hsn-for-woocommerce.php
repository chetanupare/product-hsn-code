<?php
/**
 * Plugin Name: HSN for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/hsn-for-woocommerce/
 * Description: Complete HSN (Harmonized System of Nomenclature) code management for WooCommerce with bulk operations, tax calculations, and GST compliance for Indian businesses.
 * Version: 1.0.0
 * Author: Chetan Upare
 * Author URI: https://github.com/chetanupare
 * Text Domain: hsn-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HSN_WC_VERSION', '1.0.0');
define('HSN_WC_PLUGIN_FILE', __FILE__);
define('HSN_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HSN_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HSN_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'hsn_wc_woocommerce_missing_notice');
    return;
}

/**
 * WooCommerce missing notice
 */
function hsn_wc_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('HSN for WooCommerce requires WooCommerce to be installed and active.', 'hsn-for-woocommerce'); ?></p>
    </div>
    <?php
}

/**
 * Main HSN for WooCommerce Class
 */
class HSN_For_WooCommerce {
    
    /**
     * Single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Main HSN for WooCommerce Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->includes();
    }
    
    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Include required core files
     */
    public function includes() {
        include_once HSN_WC_PLUGIN_DIR . 'includes/class-hsn-wc-admin.php';
        include_once HSN_WC_PLUGIN_DIR . 'includes/class-hsn-wc-frontend.php';
        include_once HSN_WC_PLUGIN_DIR . 'includes/class-hsn-wc-product.php';
        include_once HSN_WC_PLUGIN_DIR . 'includes/class-hsn-wc-import-export.php';
        include_once HSN_WC_PLUGIN_DIR . 'includes/class-hsn-wc-tax-calculator.php';
        include_once HSN_WC_PLUGIN_DIR . 'includes/class-hsn-wc-database.php';
        include_once HSN_WC_PLUGIN_DIR . 'includes/functions.php';
    }
    
    /**
     * Init HSN for WooCommerce when WordPress Initializes
     */
    public function init() {
        // Initialize classes
        new HSN_WC_Admin();
        new HSN_WC_Frontend();
        new HSN_WC_Product();
        new HSN_WC_Import_Export();
        new HSN_WC_Tax_Calculator();
        new HSN_WC_Database();
        
        // Load plugin textdomain
        $this->load_textdomain();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('hsn-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Create upload directory
        $this->create_upload_directory();
        
        // Schedule events
        if (!wp_next_scheduled('hsn_wc_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'hsn_wc_daily_cleanup');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('hsn_wc_daily_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // HSN Codes table
        $table_name = $wpdb->prefix . 'hsn_wc_codes';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            hsn_code varchar(20) NOT NULL,
            description text,
            gst_rate decimal(5,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY hsn_code (hsn_code)
        ) $charset_collate;";
        
        // Import/Export logs table
        $table_logs = $wpdb->prefix . 'hsn_wc_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            operation_type varchar(20) NOT NULL,
            file_name varchar(255),
            records_processed int DEFAULT 0,
            success_count int DEFAULT 0,
            error_count int DEFAULT 0,
            user_id bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_logs);
        
        // Insert default HSN codes
        $this->insert_default_hsn_codes();
    }
    
    /**
     * Insert default HSN codes
     */
    private function insert_default_hsn_codes() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hsn_wc_codes';
        
        $default_codes = array(
            array('hsn_code' => '1001', 'description' => 'Wheat and meslin', 'gst_rate' => 0.00),
            array('hsn_code' => '1006', 'description' => 'Rice', 'gst_rate' => 0.00),
            array('hsn_code' => '6403', 'description' => 'Footwear with outer soles of rubber', 'gst_rate' => 18.00),
            array('hsn_code' => '6404', 'description' => 'Footwear with outer soles of textile materials', 'gst_rate' => 18.00),
            array('hsn_code' => '8517', 'description' => 'Telephone sets and other apparatus', 'gst_rate' => 18.00),
        );
        
        foreach ($default_codes as $code) {
            $wpdb->insert($table_name, $code);
        }
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'hsn_wc_display_position' => 'after_title',
            'hsn_wc_display_format' => 'HSN Code: {code}',
            'hsn_wc_color' => '#333333',
            'hsn_wc_font_size' => '14',
            'hsn_wc_font_weight' => 'normal',
            'hsn_wc_background_color' => '#f8f9fa',
            'hsn_wc_border_color' => '#dee2e6',
            'hsn_wc_enable_tax_calculation' => 'yes',
            'hsn_wc_show_gst_rate' => 'yes',
            'hsn_wc_cache_duration' => '3600',
            'hsn_wc_version' => HSN_WC_VERSION
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Create upload directory
     */
    private function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $woohsn_dir = $upload_dir['basedir'] . '/hsn-wc';
        
        if (!file_exists($woohsn_dir)) {
            wp_mkdir_p($woohsn_dir);
            
            // Create .htaccess file for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files ~ \"\\.(csv|xml|json)$\">\n";
            $htaccess_content .= "    Order allow,deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($woohsn_dir . '/.htaccess', $htaccess_content);
        }
    }
}

/**
 * Main instance of HSN for WooCommerce
 */
function HSN_For_WooCommerce() {
    return HSN_For_WooCommerce::instance();
}

// Global for backwards compatibility
$GLOBALS['hsn_wc'] = HSN_For_WooCommerce();