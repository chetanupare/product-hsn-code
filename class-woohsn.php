<?php
/**
 * Plugin Name: WooHSN
 * Plugin URI: https://wordpress.org/plugins/woohsn/
 * Description: Smart HSN tagging system for WooCommerce stores with HPOS support. Automate GST readiness with minimal effort.
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
 *
 * @package WooHSN
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WOOHSN_VERSION', '1.0.0' );
define( 'WOOHSN_PLUGIN_FILE', __FILE__ );
define( 'WOOHSN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOOHSN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WOOHSN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check if WooCommerce is active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	add_action( 'admin_notices', 'woohsn_woocommerce_missing_notice' );
	return;
}

// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * WooCommerce missing notice.
 */
function woohsn_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'WooHSN requires WooCommerce to be installed and active.', 'woohsn' ); ?></p>
	</div>
	<?php
}

/**
 * Main WooHSN Class.
 */
class WooHSN {

	/**
	 * Single instance of the class.
	 *
	 * @var WooHSN
	 */
	protected static $instance = null;

	/**
	 * Main WooHSN Instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
		$this->includes();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Activation hook.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Deactivation hook.
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Initialize plugin.
		add_action( 'init', array( $this, 'init_plugin' ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once WOOHSN_PLUGIN_DIR . 'includes/functions.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-database.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-admin.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-product.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-frontend.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-order.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-tax-calculator.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-import-export.php';
		require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-hpos-compatibility.php';
	}

	/**
	 * Initialize plugin.
	 */
	public function init_plugin() {
		// Initialize classes.
		WooHSN_Database::instance();
		WooHSN_Admin::instance();
		WooHSN_Product::instance();
		WooHSN_Frontend::instance();
		WooHSN_Order::instance();
		WooHSN_Tax_Calculator::instance();
		WooHSN_Import_Export::instance();
		WooHSN_HPOS_Compatibility::instance();

		// Load text domain.
		load_plugin_textdomain( 'woohsn', false, dirname( WOOHSN_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		$this->create_tables();
		$this->add_default_options();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 */
	private function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// HSN codes table.
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

		// Import/Export logs table.
		$table_name_logs = $wpdb->prefix . 'woohsn_logs';
		$sql_logs = "CREATE TABLE $table_name_logs (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			operation_type varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			message text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql_logs );

		$this->insert_default_hsn_codes();
	}

	/**
	 * Insert default HSN codes.
	 */
	private function insert_default_hsn_codes() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woohsn_codes';

		$default_codes = array(
			array(
				'hsn_code'    => '9971',
				'description' => 'Jewellery, Goldsmiths wares and other articles',
				'gst_rate'    => 3.00,
			),
			array(
				'hsn_code'    => '7113',
				'description' => 'Articles of jewellery and parts thereof, of precious metal or of metal clad with precious metal',
				'gst_rate'    => 3.00,
			),
			array(
				'hsn_code'    => '7114',
				'description' => 'Articles of goldsmiths or silversmiths wares and parts thereof',
				'gst_rate'    => 3.00,
			),
		);

		foreach ( $default_codes as $code ) {
			$wpdb->insert( $table_name, $code );
		}
	}

	/**
	 * Add default options.
	 */
	private function add_default_options() {
		$default_options = array(
			'woohsn_enable_hsn_display'     => 'yes',
			'woohsn_display_format'        => 'HSN Code: {code}',
			'woohsn_enable_tax_calculator' => 'yes',
			'woohsn_cache_duration'        => 3600,
			'woohsn_enable_import_export'  => 'yes',
		);

		foreach ( $default_options as $option => $value ) {
			if ( false === get_option( $option ) ) {
				add_option( $option, $value );
			}
		}
	}
}

// Initialize the plugin.
WooHSN::instance();
