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

// Include required files.
require_once WOOHSN_PLUGIN_DIR . 'includes/functions.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-database.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-admin.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-product.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-frontend.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-order.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-tax-calculator.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-import-export.php';
require_once WOOHSN_PLUGIN_DIR . 'includes/class-woohsn-hpos-compatibility.php';
require_once WOOHSN_PLUGIN_DIR . 'class-woohsn.php';

// Initialize the plugin.
WooHSN::instance();
