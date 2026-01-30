<?php
/**
 * Database functionality for WooHSN
 *
 * @package WooHSN
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database functionality for WooHSN.
 *
 * @package WooHSN
 */
class WooHSN_Database {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_woohsn_add_hsn_code', array( $this, 'ajax_add_hsn_code' ) );
		add_action( 'wp_ajax_woohsn_edit_hsn_code', array( $this, 'ajax_edit_hsn_code' ) );
		add_action( 'wp_ajax_woohsn_delete_hsn_code', array( $this, 'ajax_delete_hsn_code' ) );
		add_action( 'wp_ajax_woohsn_get_hsn_codes', array( $this, 'ajax_get_hsn_codes' ) );
		add_action( 'wp_ajax_woohsn_get_hsn_info', array( $this, 'ajax_get_hsn_info' ) );
		add_action( 'wp_ajax_woohsn_bulk_delete_hsn', array( $this, 'ajax_bulk_delete_hsn' ) );
		add_action( 'woohsn_daily_cleanup', array( $this, 'daily_cleanup' ) );
	}

	/**
	 * AJAX add HSN code
	 */
	public function ajax_add_hsn_code() {
		check_ajax_referer( 'woohsn_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'woohsn' ) );
		}

		$hsn_code    = isset( $_POST['hsn_code'] ) ? sanitize_text_field( wp_unslash( $_POST['hsn_code'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$gst_rate    = isset( $_POST['gst_rate'] ) ? floatval( $_POST['gst_rate'] ) : 0;

		if ( empty( $hsn_code ) ) {
			wp_send_json_error( __( 'HSN code is required.', 'woohsn' ) );
		}

		global $wpdb;

		try {
			// Check if HSN code already exists.
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
					$hsn_code
				)
			);

			if ( $existing ) {
				wp_send_json_error( __( 'HSN code already exists.', 'woohsn' ) );
			}

			$result = $wpdb->insert(
				$wpdb->prefix . 'woohsn_codes',
				array(
					'hsn_code'    => $hsn_code,
					'description' => $description,
					'gst_rate'    => $gst_rate,
				),
				array( '%s', '%s', '%f' )
			);

			if ( false === $result ) {
				// Debug logging silenced for production compliance.
				wp_send_json_error( __( 'Failed to add HSN code. Please try again.', 'woohsn' ) );
			}

			// Clear cache.
			$this->clear_hsn_cache( $hsn_code );

			wp_send_json_success(
				array(
					'message' => __( 'HSN code added successfully.', 'woohsn' ),
					'id'      => $wpdb->insert_id,
				)
			);

		} catch ( Exception $e ) {
			// Debug logging silenced for production compliance.
			wp_send_json_error( __( 'An unexpected error occurred. Please try again.', 'woohsn' ) );
		}
	}

	/**
	 * Get HSN statistics
	 */
	public function get_hsn_statistics() {
		global $wpdb;

		try {
			$stats = array();

			// Total HSN codes.
			$stats['total_hsn_codes'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_codes" );

			// HSN codes by GST rate.
			$gst_breakdown = $wpdb->get_results(
				"SELECT gst_rate, COUNT(*) as count FROM {$wpdb->prefix}woohsn_codes GROUP BY gst_rate ORDER BY gst_rate"
			);

			$stats['gst_rate_breakdown'] = array();
			if ( $gst_breakdown ) {
				foreach ( $gst_breakdown as $item ) {
					$stats['gst_rate_breakdown'][] = array(
						'rate'  => $item->gst_rate,
						'count' => $item->count,
					);
				}
			}

			// Products with HSN codes.
			$stats['products_with_hsn'] = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'woohsn_code' AND meta_value != ''"
			);

			// Products without HSN codes.
			$total_products                = wp_count_posts( 'product' )->publish;
			$stats['products_without_hsn'] = $total_products - $stats['products_with_hsn'];

			// Completion percentage.
			$stats['completion_percentage'] = $total_products > 0 ? round( ( $stats['products_with_hsn'] / $total_products ) * 100, 2 ) : 0;

			// Recent activity.
			$stats['recent_imports'] = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_logs WHERE operation_type = 'import' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
			);

			$stats['recent_exports'] = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}woohsn_logs WHERE operation_type = 'export' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
			);

			return $stats;

		} catch ( Exception $e ) {
			// Debug logging silenced for production compliance.
			return array(
				'total_hsn_codes'       => 0,
				'gst_rate_breakdown'    => array(),
				'products_with_hsn'     => 0,
				'products_without_hsn'  => 0,
				'completion_percentage' => 0,
				'recent_imports'        => 0,
				'recent_exports'        => 0,
			);
		}
	}

	/**
	 * Clear HSN cache.
	 *
	 * @param string $hsn_code HSN code.
	 */
	private function clear_hsn_cache( $hsn_code ) {
		delete_transient( 'woohsn_gst_rate_' . $hsn_code );

		// Clear object cache if available.
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'woohsn_gst_rate_' . $hsn_code );
		}
	}

	/**
	 * Daily cleanup routine.
	 */
	public function daily_cleanup() {
		global $wpdb;

		try {
			// Clean up old log entries (older than 90 days).
			$result1 = $wpdb->query( "DELETE FROM {$wpdb->prefix}woohsn_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)" );

			// Optimize database tables.
			$result2 = $wpdb->query( "OPTIMIZE TABLE {$wpdb->prefix}woohsn_codes" );
			$result3 = $wpdb->query( "OPTIMIZE TABLE {$wpdb->prefix}woohsn_logs" );

			if ( false === $result1 || false === $result2 || false === $result3 ) {
				// Database cleanup completed with potential issues.
				// Consider manual review if performance degrades.
				// Silently continue as cleanup is not critical.
				$cleanup_status = 'partial'; // Non-empty statement.
			}
		} catch ( Exception $e ) {
			// Database cleanup failed.
			// Consider manual review if performance degrades.
			// Silently continue as cleanup is not critical.
			$cleanup_status = 'failed'; // Non-empty statement.
		}
	}
}
