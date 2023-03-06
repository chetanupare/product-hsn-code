<?php
// Import/Export function for Product HSN Codes
function product_hsn_code_import_export() {
  echo "import triggerd";
    // Handle import
    if (isset($_POST['product_hsn_code_import'])) {
        // Retrieve file data
        $file = $_FILES['product_hsn_code_file'];
        $file_name = $file['name'];
        $file_temp = $file['tmp_name'];
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

        // Check if file is CSV
        if (strtolower($file_ext) === 'csv') {
            // Read CSV data
            $csv_data = array_map('str_getcsv', file($file_temp));

            // Update HSN codes
            foreach ($csv_data as $data) {
                $product_id = $data[0];
                $hsn_code = $data[1];
                update_post_meta($product_id, 'product_hsn_code', $hsn_code);
            }

            // Redirect to settings page with success message
            wp_redirect(add_query_arg(array('page' => 'product_hsn_code_settings', 'imported' => count($csv_data)), admin_url('admin.php')));
            exit;
        } else {
            // Redirect to settings page with error message
            wp_redirect(add_query_arg(array('page' => 'product_hsn_code_settings', 'import_error' => 'File must be a CSV'), admin_url('admin.php')));
            exit;
        }
    }

    // Handle export
    if (isset($_POST['product_hsn_code_export'])) {
      echo "export triggered";
      error_log("Export triggered");

        // Retrieve all products
        $products = get_posts(array('post_type' => 'product', 'numberposts' => -1));

        // Create CSV data
        $csv_data = array();
        foreach ($products as $product) {
            $product_id = $product->ID;
            $product_name = get_the_title($product_id);
            $product_hsn_code = get_post_meta($product_id, 'product_hsn_code', true);
            if ($product_hsn_code) {
                $csv_data[] = array($product_id, $product_name, $product_hsn_code);
            }
        }

        // Set headers for file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="product_hsn_codes.csv"');

        // Create file pointer
        $output = fopen('php://output', 'w');

        // Add CSV data to file
        foreach ($csv_data as $data) {
            fputcsv($output, $data);
        }

        // Close file pointer
        fclose($output);

        // Stop script execution
        exit;
    }
}
