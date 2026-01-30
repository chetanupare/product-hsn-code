<?php
/**
 * Main WooHSN Class.
 *
 * @package WooHSN
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main WooHSN Class.
 *
 * @package WooHSN
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
		register_activation_hook( WOOHSN_PLUGIN_FILE, array( $this, 'activate' ) );

		// Deactivation hook.
		register_deactivation_hook( WOOHSN_PLUGIN_FILE, array( $this, 'deactivate' ) );

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
		$sql        = "CREATE TABLE $table_name (
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
		$sql_logs        = "CREATE TABLE $table_name_logs (
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( $table_name, $code );
			// phpcs:ignore
		}
	}

	/**
	 * Add default options.
	 */
	private function add_default_options() {
		$default_options = array(
			'woohsn_enable_hsn_display'    => 'yes',
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
