<?php
/**
 * Helper functions for WooHSN
 *
 * @package WooHSN
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get HSN code for product.
 *
 * @param int $product_id Product ID.
 * @return string HSN code.
 */
function woohsn_get_product_hsn_code( $product_id ) {
	return get_post_meta( $product_id, 'woohsn_code', true );
}

/**
 * Get GST rate for HSN code.
 *
 * @param string $hsn_code HSN code.
 * @return float GST rate.
 */
function woohsn_get_gst_rate( $hsn_code ) {
	global $wpdb;

	if ( empty( $hsn_code ) ) {
		return 0;
	}

	$cache_key = 'woohsn_gst_rate_' . $hsn_code;
	$gst_rate  = get_transient( $cache_key );

	if ( false === $gst_rate ) {
		try {
			$gst_rate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT gst_rate FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
					$hsn_code
				)
			);

			$gst_rate       = $gst_rate ? floatval( $gst_rate ) : 0;
			$cache_duration = get_option( 'woohsn_cache_duration', 3600 );
			set_transient( $cache_key, $gst_rate, $cache_duration );

		} catch ( Exception $e ) {
			// Debug logging silenced for production compliance.
			$gst_rate = 0;
		}
	}

	return floatval( $gst_rate );
}

/**
 * Format HSN code display.
 *
 * @param string      $hsn_code HSN code.
 * @param string|null $format Display format.
 * @return string Formatted HSN display.
 */
function woohsn_format_hsn_display( $hsn_code, $format = null ) {
	if ( empty( $hsn_code ) ) {
		return '';
	}

	if ( ! $format ) {
		$format = get_option( 'woohsn_display_format', 'HSN Code: {code}' );
	}

	return str_replace( '{code}', $hsn_code, $format );
}

/**
 * Get product GST calculation.
 *
 * @param int   $product_id Product ID.
 * @param float $price      Product price.
 * @param int   $quantity   Product quantity.
 * @return array  GST calculation data.
 */
function woohsn_calculate_product_gst( $product_id, $price = null, $quantity = 1 ) {
	if ( ! $price ) {
		$product = wc_get_product( $product_id );
		$price   = $product ? $product->get_price() : 0;
	}

	$hsn_code = woohsn_get_product_hsn_code( $product_id );
	$gst_rate = woohsn_get_gst_rate( $hsn_code );

	$subtotal   = $price * $quantity;
	$gst_amount = ( $subtotal * $gst_rate ) / 100;
	$total      = $subtotal + $gst_amount;

	return array(
		'hsn_code'   => $hsn_code,
		'gst_rate'   => $gst_rate,
		'subtotal'   => $subtotal,
		'gst_amount' => $gst_amount,
		'total'      => $total,
	);
}

/**
 * Check if HSN code exists in database.
 *
 * @param string $hsn_code HSN code.
 * @return bool True if exists, false otherwise.
 */
function woohsn_hsn_code_exists( $hsn_code ) {
	global $wpdb;

	if ( empty( $hsn_code ) ) {
		return false;
	}

	try {
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
				$hsn_code
			)
		);

		return ! empty( $exists );

	} catch ( Exception $e ) {
		// Debug logging silenced for production compliance.
		return false;
	}
}

/**
 * Get HSN code description.
 *
 * @param string $hsn_code HSN code.
 * @return string HSN code description.
 */
function woohsn_get_hsn_description( $hsn_code ) {
	global $wpdb;

	if ( empty( $hsn_code ) ) {
		return '';
	}

	try {
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT description FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
				$hsn_code
			)
		);

	} catch ( Exception $e ) {
		// Debug logging silenced for production compliance.
		return '';
	}
}

/**
 * Validate HSN code format.
 *
 * @param string $hsn_code HSN code to validate.
 * @return bool True if valid, false otherwise.
 */
function woohsn_validate_hsn_code( $hsn_code ) {
	// HSN codes are typically 4-8 digit numbers.
	return preg_match( '/^[0-9]{4,8}$/', $hsn_code );
}

/**
 * Log plugin activity.
 *
 * @param string $message Log message.
 * @param string $level   Log level (info, warning, error).
 */
function woohsn_log_activity( $message, $level = 'info' ) {
	// Intentional: $level parameter is not used in current implementation.
	unset( $level );

	// Debug logging silenced for production compliance.
}

/**
 * Get plugin version.
 *
 * @return string Plugin version.
 */
function woohsn_get_version() {
	return WOOHSN_VERSION;
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool True if WooCommerce is active.
 */
function woohsn_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Get all GST rates.
 *
 * @return array Array of GST rates.
 */
function woohsn_get_all_gst_rates() {
	global $wpdb;

	$rates = $wpdb->get_col( "SELECT DISTINCT gst_rate FROM {$wpdb->prefix}woohsn_codes ORDER BY gst_rate ASC" );

	return array_map( 'floatval', $rates );
}

/**
 * Check if HPOS is enabled.
 *
 * @return bool True if HPOS is enabled.
 */
function woohsn_is_hpos_enabled() {
	return WooHSN_HPOS_Compatibility::is_hpos_enabled();
}

/**
 * Get order HSN summary (HPOS compatible).
 *
 * @param int $order_id Order ID.
 * @return mixed Order HSN summary.
 */
function woohsn_get_order_hsn_summary( $order_id ) {
	return WooHSN_HPOS_Compatibility::get_order_meta( $order_id, '_woohsn_summary', true );
}

/**
 * Get order total GST amount (HPOS compatible).
 *
 * @param int $order_id Order ID.
 * @return mixed Total GST amount.
 */
function woohsn_get_order_total_gst( $order_id ) {
	return WooHSN_HPOS_Compatibility::get_order_meta( $order_id, '_woohsn_total_gst', true );
}

/**
 * Update order HSN data (HPOS compatible).
 *
 * @param int   $order_id Order ID.
 * @param array $hsn_data HSN data.
 * @return bool Update result.
 */
function woohsn_update_order_hsn_data( $order_id, $hsn_data ) {
	return WooHSN_HPOS_Compatibility::update_order_meta( $order_id, '_woohsn_summary', $hsn_data );
}

/**
 * Get orders with HSN data (HPOS compatible).
 *
 * @param array $args Query arguments.
 * @return array Orders with HSN data.
 */
function woohsn_get_orders_with_hsn( $args = array() ) {
	$default_args = array(
		'status'     => 'any',
		'limit'      => -1,
		'meta_query' => array(
			array(
				'key'     => '_woohsn_summary',
				'compare' => 'EXISTS',
			),
		),
	);

	$args = wp_parse_args( $args, $default_args );

	return WooHSN_HPOS_Compatibility::get_orders( $args );
}

/**
 * Check if order has HSN data (HPOS compatible).
 *
 * @param int $order_id Order ID.
 * @return bool True if order has HSN data.
 */
function woohsn_order_has_hsn_data( $order_id ) {
	$hsn_summary = woohsn_get_order_hsn_summary( $order_id );
	return ! empty( $hsn_summary );
}

/**
 * Get HPOS compatibility status.
 *
 * @return string HPOS compatibility status.
 */
function woohsn_get_hpos_status() {
	return WooHSN_HPOS_Compatibility::get_supported_features();
}
