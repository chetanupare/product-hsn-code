<?php
/**
 * Plugin Name: WooHSN
 * Plugin URI: https://wordpress.org/plugins/woohsn/
 * Description: Smart HSN tagging system for WooCommerce stores. Automate GST readiness with minimal effort.
 * Version: 1.0.0
 * Author: Chetan Upare
 * Author URI: https://profiles.wordpress.org/chetanupare/
 * Text Domain: woohsn
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
define('WOOHSN_VERSION', '1.0.0');
define('WOOHSN_PLUGIN_FILE', __FILE__);
define('WOOHSN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOOHSN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOOHSN_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'woohsn_woocommerce_missing_notice');
    return;
}

/**
 * WooCommerce missing notice
 */
function woohsn_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('WooHSN requires WooCommerce to be installed and active.', 'woohsn'); ?></p>
    </div>
    <?php
}

/**
 * Main WooHSN Class
 */
class WooHSN {
    
    /**
     * Single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Main WooHSN Instance
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
        include_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-admin.php';
        include_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-frontend.php';
        include_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-product.php';
        include_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-import-export.php';
        include_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-tax-calculator.php';
        include_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-database.php';
        include_once WOOHSN_PLUGIN_DIR . 'includes/functions.php';
    }
    
    /**
     * Init WooHSN when WordPress Initializes
     */
    public function init() {
        // Initialize classes
        new WooHSN_Admin();
        new WooHSN_Frontend();
        new WooHSN_Product();
        new WooHSN_Import_Export();
        new WooHSN_Tax_Calculator();
        new WooHSN_Database();
        
        // Load plugin textdomain
        $this->load_textdomain();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('woohsn', false, dirname(plugin_basename(__FILE__)) . '/languages/');
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
        if (!wp_next_scheduled('woohsn_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'woohsn_daily_cleanup');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('woohsn_daily_cleanup');
        
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
        $table_name = $wpdb->prefix . 'woohsn_codes';
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
        $table_logs = $wpdb->prefix . 'woohsn_logs';
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
        
        $table_name = $wpdb->prefix . 'woohsn_codes';
        
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
            'woohsn_display_position' => 'after_title',
            'woohsn_display_format' => 'HSN Code: {code}',
            'woohsn_color' => '#333333',
            'woohsn_font_size' => '14',
            'woohsn_font_weight' => 'normal',
            'woohsn_background_color' => '#f8f9fa',
            'woohsn_border_color' => '#dee2e6',
            'woohsn_enable_tax_calculation' => 'yes',
            'woohsn_show_gst_rate' => 'yes',
            'woohsn_cache_duration' => '3600',
            'woohsn_version' => WOOHSN_VERSION
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
        $woohsn_dir = $upload_dir['basedir'] . '/woohsn';
        
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
 * Main instance of WooHSN
 */
function WooHSN() {
    return WooHSN::instance();
}

// Global for backwards compatibility
$GLOBALS['woohsn'] = WooHSN();