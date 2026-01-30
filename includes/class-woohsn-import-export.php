<?php
/**
 * Import/Export functionality for WooHSN
 *
 * @package WooHSN
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import/Export functionality for WooHSN.
 *
 * @package WooHSN
 */
class WooHSN_Import_Export {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_woohsn_import_csv', array( $this, 'ajax_import_csv' ) );
		add_action( 'wp_ajax_woohsn_export_csv', array( $this, 'ajax_export_csv' ) );
	}

	/**
	 * AJAX import CSV
	 */
	public function ajax_import_csv() {
		check_ajax_referer( 'woohsn_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'woohsn' ) );
		}

		if ( ! isset( $_FILES['csv_file'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'woohsn' ) );
		}

		$file = isset( $_FILES['csv_file'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_FILES['csv_file'] ) ) : array();

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error( __( 'File upload error.', 'woohsn' ) );
		}

		try {
			$csv_data = array_map( 'str_getcsv', file( $file['tmp_name'] ) );
			$headers  = array_shift( $csv_data );

			$success_count = 0;
			$error_count   = 0;

			global $wpdb;

			foreach ( $csv_data as $row ) {
				if ( count( $row ) >= 3 ) {
					$hsn_code    = sanitize_text_field( $row[0] );
					$description = sanitize_textarea_field( $row[1] );
					$gst_rate    = floatval( $row[2] );

					$result = $wpdb->insert(
						$wpdb->prefix . 'woohsn_codes',
						array(
							'hsn_code'    => $hsn_code,
							'description' => $description,
							'gst_rate'    => $gst_rate,
						),
						array( '%s', '%s', '%f' )
					);

					if ( true === $result ) {
						++$success_count;
					} else {
						++$error_count;
						error_log( '[WooHSN] Import failed for HSN code: ' . $hsn_code . ' - ' . $wpdb->last_error );
					}
				} else {
					++$error_count;
				}
			}

			/* translators: %1$d: number of successful imports, %2$d: number of errors */
			wp_send_json_success(
				array(
					'message'       => sprintf(
						__( 'Import completed. %1$d records imported, %2$d errors.', 'woohsn' ),
						$success_count,
						$error_count
					),
					'success_count' => $success_count,
					'error_count'   => $error_count,
				)
			);

		} catch ( Exception $e ) {
			error_log( '[WooHSN] Exception in ajax_import_csv: ' . $e->getMessage() );
			wp_send_json_error( __( 'Import failed due to an unexpected error.', 'woohsn' ) );
		}
	}

	/**
	 * AJAX export CSV.
	 *
	 * @throws Exception When file operations fail.
	 */
	public function ajax_export_csv() {
		check_ajax_referer( 'woohsn_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'woohsn' ) );
		}

		try {
			global $wpdb;

			$hsn_codes = $wpdb->get_results( "SELECT hsn_code, description, gst_rate FROM {$wpdb->prefix}woohsn_codes ORDER BY hsn_code ASC" );

			if ( ! $hsn_codes ) {
				error_log( '[WooHSN] No HSN codes found for export' );
				wp_send_json_error( __( 'No HSN codes found to export.', 'woohsn' ) );
			}

			$filename = 'woohsn_codes_export_' . gmdate( 'Y-m-d_H-i-s' ) . '.csv';

			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

			$output = fopen( 'php://output', 'w' );

			if ( false === $output ) {
				throw new Exception( 'Failed to open output stream' );
			}

			// Headers.
			fputcsv( $output, array( 'HSN Code', 'Description', 'GST Rate' ) );

			// Data.
			foreach ( $hsn_codes as $hsn_code ) {
				fputcsv(
					$output,
					array(
						$hsn_code->hsn_code,
						$hsn_code->description,
						$hsn_code->gst_rate,
					)
				);
			}

			fclose( $output );
			exit;

		} catch ( Exception $e ) {
			error_log( '[WooHSN] Exception in ajax_export_csv: ' . $e->getMessage() );
			wp_send_json_error( __( 'Export failed due to an unexpected error.', 'woohsn' ) );
		}
	}
}
