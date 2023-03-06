<?php

/*
Plugin Name: Product HSN Code
Plugin URI: https://www.example.com/
Description: This plugin adds a meta field for HSN code to products in WooCommerce.
Version: 1.0.0
Author: Your Name
Author URI: https://www.example.com/
*/

defined('ABSPATH') or die('No direct script access allowed');

require_once( plugin_dir_path( __FILE__ ) . 'inc/hsn-import-export.php' );


function product_hsn_code_enqueue_styles()
{
    wp_enqueue_style('product-hsn-code-style-css', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('product-hsn-code-style-js', plugin_dir_url(__FILE__) . 'style.js');
    // Enqueue Spectrum.js
    wp_enqueue_script('spectrum-js', 'https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.js', array('jquery'), '1.8.0', true);
    // Enqueue Spectrum.css
    wp_enqueue_style('spectrum-css', 'https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.css', array(), '1.8.0', 'all');
    // Enqueue color picker scripts and styles
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
}
add_action('wp_enqueue_scripts', 'product_hsn_code_enqueue_styles');
add_action('admin_enqueue_scripts', 'product_hsn_code_enqueue_styles');

function product_hsn_code_add_meta_box()
{
    add_meta_box(
        'product-hsn-code-meta-box',
        'HSN Code',
        'product_hsn_code_meta_box_callback',
        'product',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'product_hsn_code_add_meta_box');

function product_hsn_code_meta_box_callback($post)
{
    wp_nonce_field(basename(__FILE__), 'product_hsn_code_nonce');
    $hsn_code = get_post_meta($post->ID, 'product_hsn_code', true);
    echo '<label for="product-hsn-code">Enter HSN Code:</label> ';
    echo '<input type="text" id="product-hsn-code" name="product_hsn_code" value="' . esc_attr($hsn_code) . '" size="25" />';
}

function product_hsn_code_save_meta_box_data($post_id)
{
    if (!isset($_POST['product_hsn_code_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['product_hsn_code_nonce'], basename(__FILE__))) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['post_type']) && 'product' == $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
    if (!isset($_POST['product_hsn_code'])) {
        return;
    }
    $hsn_code = sanitize_text_field($_POST['product_hsn_code']);
    update_post_meta($post_id, 'product_hsn_code', $hsn_code);
}
add_action('save_post', 'product_hsn_code_save_meta_box_data');

function product_hsn_code_display()
{
    global $product;
    $hsn_code = get_post_meta($product->get_id(), 'product_hsn_code', true);
    $color = get_option('product_hsn_code_color');
    $typography = get_option('product_hsn_code_typography');
    $style = get_option('product_hsn_code_style'); // get the value of product_hsn_code_style option
    if ($hsn_code) {
        echo '<div class="product-hsn-code" style="color: ' . $color . '; font-family: ' . $typography . '; font-style: ' . $style . ';">';
        echo '<strong>HSN Code:</strong> ' . esc_html($hsn_code);
        echo '</div>';
    }
}
add_action('woocommerce_single_product_summary', 'product_hsn_code_display', 6);


function product_hsn_code_add_settings()
{
    add_settings_field(
        'product_hsn_code_display',
        'Display HSN code on product page',
        'product_hsn_code_display_callback',
        'woocommerce',
        'product',
        array('label_for' => 'product_hsn_code_display')
    );
    register_setting('woocommerce', 'product_hsn_code_display');
}
add_action('woocommerce_product_options_general_product_data', 'product_hsn_code_add_settings');

function product_hsn_code_add_menu_item()
{
    add_submenu_page(
        'woocommerce',
        'Product HSN Code',
        'Product HSN Code',
        'manage_options',
        'product-hsn-code',
        'product_hsn_code_page_callback'
    );
}
add_action('admin_menu', 'product_hsn_code_add_menu_item');
function product_hsn_code_register_settings()
{
    register_setting('product-hsn-code-settings-group', 'product_hsn_code_tax_rate');
}
add_action('admin_init', 'product_hsn_code_register_settings');

function product_hsn_code_page_callback()
{

?>
    <div class="wrap">
        <h1><?php esc_html_e('Product HSN Code Settings', 'product-hsn-code'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('product-hsn-code-settings-group'); ?>
            <?php do_settings_sections('product-hsn-code-settings-group'); ?>
            <h2 class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'product-hsn-code'); ?></a>
                <a href="#style" class="nav-tab"><?php esc_html_e('Style', 'product-hsn-code'); ?></a>
                <a href="#bulk" class="nav-tab"><?php esc_html_e('Bulk Operations', 'product-hsn-code'); ?></a>

            </h2>
            <div id="general" class="product-hsn-code-tab">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Tax Rate', 'product-hsn-code'); ?></th>
                            <td>
                                <input type="text" name="product_hsn_code_tax_rate" value="<?php echo esc_attr(get_option('product_hsn_code_tax_rate')); ?>" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="style" class="product-hsn-code-tab" style="display:none;">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Color', 'product-hsn-code'); ?></th>
                            <td>
                                <input type="text" name="product_hsn_code_color" class="product-hsn-code-color" value="<?php echo esc_attr(get_option('product_hsn_code_color')); ?>" data-product-hsn-code-color="<?php echo esc_attr(get_option('product_hsn_code_color')); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Typography', 'product-hsn-code'); ?></th>
                            <td>
                                <select name="product_hsn_code_typography">
                                    <?php
                                    $typography_options = array(
                                        'Arial' => 'Arial',
                                        'Helvetica' => 'Helvetica',
                                        'Times New Roman' => 'Times New Roman',
                                        'Verdana' => 'Verdana',
                                        'Georgia' => 'Georgia'
                                    );
                                    $selected_typography = get_option('product_hsn_code_typography');
                                    foreach ($typography_options as $option_value => $option_label) {
                                        $selected = ($option_value == $selected_typography) ? 'selected' : '';
                                        echo '<option value="' . $option_value . '" ' . $selected . '>' . $option_label . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Style', 'product-hsn-code'); ?></th>
                            <td>
                                <select name="product_hsn_code_style" id="product-hsn-code-style">
                                    <?php
                                    $style_options = array(
                                        'default' => 'Default',
                                        'bold' => 'Bold',
                                        'italic' => 'Italic',
                                        'underline' => 'Underline'
                                    );
                                    $selected_style = get_option('product_hsn_code_style', 'default');
                                    foreach ($style_options as $option_value => $option_label) {
                                        $selected = ($option_value == $selected_style) ? 'selected' : '';
                                        echo '<option value="' . $option_value . '" ' . $selected . '>' . $option_label . '</option>';
                                    }
                                    ?>
                                </select>
                                <div id="product-hsn-code-style-preview"></div>
                            </td>
                        </tr>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(esc_html__('Save Changes', 'product-hsn-code')); ?>
        </form>
    </div>
    <div id="bulk" class="product-hsn-code-tab" style="display:none;">
      <form method="post" enctype="multipart/form-data" action="<?php echo plugin_dir_url( __FILE__ ) . 'inc/hsn-import-export.php'; ?>">
        <label for="product-hsn-code-csv">CSV File:</label>
        <input type="file" name="product_hsn_code_csv" id="product-hsn-code-csv" />
        <br />
        <input type="submit" name="product_hsn_code_import" value="Import" />
        <input type="submit" name="product_hsn_code_export" value="Export" />
        </form>
    </div>
<?php
}

function product_hsn_code_settings_init()
{
    register_setting('product-hsn-code-settings-group', 'product_hsn_code_tax_rate');
    register_setting('product-hsn-code-settings-group', 'product_hsn_code_color');
    register_setting('product-hsn-code-settings-group', 'product_hsn_code_typography');
    register_setting('product-hsn-code-settings-group', 'product_hsn_code_style');
}

add_action('admin_init', 'product_hsn_code_settings_init');


$tax_rate = get_option('product_hsn_code_tax_rate');
