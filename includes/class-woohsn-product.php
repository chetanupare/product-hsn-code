<?php
/**
 * Product functionality for WooHSN
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooHSN_Product {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_options'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_options'));
        add_filter('manage_product_posts_columns', array($this, 'add_product_column'));
        add_action('manage_product_posts_custom_column', array($this, 'display_product_column'), 10, 2);
        add_filter('manage_edit-product_sortable_columns', array($this, 'make_product_column_sortable'));
        add_action('pre_get_posts', array($this, 'handle_column_sorting'));
        add_action('wp_ajax_woohsn_suggest_hsn', array($this, 'ajax_suggest_hsn'));
        add_action('wp_ajax_woohsn_get_hsn_info', array($this, 'ajax_get_hsn_info'));
    }
    
    /**
     * Add meta box
     */
    public function add_meta_box() {
        add_meta_box(
            'woohsn-meta-box',
            __('HSN Code Information', 'woohsn'),
            array($this, 'meta_box_callback'),
            'product',
            'side',
            'high'
        );
    }
    
    /**
     * Meta box callback
     */
    public function meta_box_callback($post) {
        wp_nonce_field(basename(__FILE__), 'woohsn_nonce');
        
        $hsn_code = get_post_meta($post->ID, 'woohsn_code', true);
        $custom_gst_rate = get_post_meta($post->ID, 'woohsn_custom_gst_rate', true);
        $enable_custom_gst = get_post_meta($post->ID, 'woohsn_enable_custom_gst', true);
        
        ?>
        <div class="woohsn-meta-box">
            <p>
                <label for="woohsn_code"><strong><?php esc_html_e('HSN Code:', 'woohsn'); ?></strong></label>
                <input type="text" id="woohsn_code" name="woohsn_code" value="<?php echo esc_attr($hsn_code); ?>" 
                       class="widefat" placeholder="<?php esc_attr_e('Enter HSN code', 'woohsn'); ?>" />
                <button type="button" id="woohsn-suggest-btn" class="button button-small">
                    <?php esc_html_e('Suggest', 'woohsn'); ?>
                </button>
            </p>
            
            <div id="woohsn-suggestions" style="display: none;">
                <p><strong><?php esc_html_e('Suggested HSN Codes:', 'woohsn'); ?></strong></p>
                <div id="woohsn-suggestions-list"></div>
            </div>
            
            <p>
                <label>
                    <input type="checkbox" id="woohsn_enable_custom_gst" name="woohsn_enable_custom_gst" 
                           value="yes" <?php checked($enable_custom_gst, 'yes'); ?> />
                    <?php esc_html_e('Use custom GST rate', 'woohsn'); ?>
                </label>
            </p>
            
            <p id="woohsn-custom-gst-field" style="<?php echo $enable_custom_gst !== 'yes' ? 'display: none;' : ''; ?>">
                <label for="woohsn_custom_gst_rate"><strong><?php esc_html_e('Custom GST Rate (%):', 'woohsn'); ?></strong></label>
                <input type="number" id="woohsn_custom_gst_rate" name="woohsn_custom_gst_rate" 
                       value="<?php echo esc_attr($custom_gst_rate); ?>" step="0.01" min="0" max="100" class="widefat" />
            </p>
            
            <div id="woohsn-hsn-info" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px; display: none;">
                <p><strong><?php esc_html_e('HSN Information:', 'woohsn'); ?></strong></p>
                <div id="woohsn-hsn-description"></div>
                <div id="woohsn-hsn-gst-rate"></div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle custom GST field
            $('#woohsn_enable_custom_gst').change(function() {
                if ($(this).is(':checked')) {
                    $('#woohsn-custom-gst-field').show();
                } else {
                    $('#woohsn-custom-gst-field').hide();
                }
            });
            
            // Suggest HSN codes
            $('#woohsn-suggest-btn').click(function() {
                var productTitle = $('#title').val();
                if (!productTitle) {
                    alert('<?php esc_js_e('Please enter a product title first.', 'woohsn'); ?>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'woohsn_suggest_hsn',
                        product_title: productTitle,
                        nonce: '<?php echo wp_create_nonce('woohsn_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var suggestions = response.data;
                            var html = '';
                            
                            suggestions.forEach(function(item) {
                                html += '<div class="woohsn-suggestion" style="margin: 5px 0; padding: 8px; border: 1px solid #ddd; cursor: pointer;">';
                                html += '<strong>' + item.hsn_code + '</strong> - ' + item.description;
                                if (item.gst_rate) {
                                    html += ' (GST: ' + item.gst_rate + '%)';
                                }
                                html += '</div>';
                            });
                            
                            $('#woohsn-suggestions-list').html(html);
                            $('#woohsn-suggestions').show();
                            
                            // Handle suggestion clicks
                            $('.woohsn-suggestion').click(function() {
                                var hsnCode = $(this).find('strong').text();
                                $('#woohsn_code').val(hsnCode);
                                $('#woohsn-suggestions').hide();
                                loadHsnInfo(hsnCode);
                            });
                        }
                    }
                });
            });
            
            // Load HSN info when code is entered
            $('#woohsn_code').blur(function() {
                var hsnCode = $(this).val();
                if (hsnCode) {
                    loadHsnInfo(hsnCode);
                }
            });
            
            function loadHsnInfo(hsnCode) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'woohsn_get_hsn_info',
                        hsn_code: hsnCode,
                        nonce: '<?php echo wp_create_nonce('woohsn_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var info = response.data;
                            $('#woohsn-hsn-description').html('<strong><?php esc_js_e('Description:', 'woohsn'); ?></strong> ' + info.description);
                            $('#woohsn-hsn-gst-rate').html('<strong><?php esc_js_e('GST Rate:', 'woohsn'); ?></strong> ' + info.gst_rate + '%');
                            $('#woohsn-hsn-info').show();
                        } else {
                            $('#woohsn-hsn-info').hide();
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_box_data($post_id) {
        if (!isset($_POST['woohsn_nonce']) || !wp_verify_nonce($_POST['woohsn_nonce'], basename(__FILE__))) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['woohsn_code'])) {
            $hsn_code = sanitize_text_field($_POST['woohsn_code']);
            update_post_meta($post_id, 'woohsn_code', $hsn_code);
        }
        
        if (isset($_POST['woohsn_enable_custom_gst'])) {
            update_post_meta($post_id, 'woohsn_enable_custom_gst', 'yes');
        } else {
            delete_post_meta($post_id, 'woohsn_enable_custom_gst');
        }
        
        if (isset($_POST['woohsn_custom_gst_rate'])) {
            $custom_gst_rate = floatval($_POST['woohsn_custom_gst_rate']);
            update_post_meta($post_id, 'woohsn_custom_gst_rate', $custom_gst_rate);
        }
    }
    
    /**
     * Add product options in general tab
     */
    public function add_product_options() {
        global $post;
        
        $hsn_code = get_post_meta($post->ID, 'woohsn_code', true);
        
        echo '<div class="options_group">';
        
        woocommerce_wp_text_input(array(
            'id' => 'woohsn_code_general',
            'label' => __('HSN Code', 'woohsn'),
            'placeholder' => __('Enter HSN code', 'woohsn'),
            'desc_tip' => true,
            'description' => __('Harmonized System of Nomenclature code for this product.', 'woohsn'),
            'value' => $hsn_code
        ));
        
        echo '</div>';
    }
    
    /**
     * Save product options
     */
    public function save_product_options($post_id) {
        if (isset($_POST['woohsn_code_general'])) {
            $hsn_code = sanitize_text_field($_POST['woohsn_code_general']);
            update_post_meta($post_id, 'woohsn_code', $hsn_code);
        }
    }
    
    /**
     * Add HSN code column to products list
     */
    public function add_product_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'price') {
                $new_columns['woohsn_code'] = __('HSN Code', 'woohsn');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display HSN code in products list column
     */
    public function display_product_column($column, $post_id) {
        if ($column === 'woohsn_code') {
            $hsn_code = get_post_meta($post_id, 'woohsn_code', true);
            
            if (!empty($hsn_code)) {
                echo '<span class="woohsn-code-display">' . esc_html($hsn_code) . '</span>';
            } else {
                echo '<span class="woohsn-no-code" style="color: #999;">—</span>';
            }
        }
    }
    
    /**
     * Make HSN code column sortable
     */
    public function make_product_column_sortable($columns) {
        $columns['woohsn_code'] = 'woohsn_code';
        return $columns;
    }
    
    /**
     * Handle column sorting
     */
    public function handle_column_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('orderby') === 'woohsn_code') {
            $query->set('meta_key', 'woohsn_code');
            $query->set('orderby', 'meta_value');
        }
    }
    
    /**
     * AJAX suggest HSN codes based on product title
     */
    public function ajax_suggest_hsn() {
        check_ajax_referer('woohsn_nonce', 'nonce');
        
        $product_title = sanitize_text_field($_POST['product_title']);
        
        // Simple keyword matching for suggestions
        $keywords = explode(' ', strtolower($product_title));
        
        global $wpdb;
        $suggestions = array();
        
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT hsn_code, description, gst_rate FROM {$wpdb->prefix}woohsn_codes 
                     WHERE LOWER(description) LIKE %s 
                     ORDER BY hsn_code ASC LIMIT 5",
                    '%' . $wpdb->esc_like($keyword) . '%'
                ));
                
                $suggestions = array_merge($suggestions, $results);
            }
        }
        
        // Remove duplicates
        $unique_suggestions = array();
        $seen_codes = array();
        
        foreach ($suggestions as $suggestion) {
            if (!in_array($suggestion->hsn_code, $seen_codes)) {
                $unique_suggestions[] = $suggestion;
                $seen_codes[] = $suggestion->hsn_code;
            }
        }
        
        wp_send_json_success(array_slice($unique_suggestions, 0, 10));
    }
    
    /**
     * AJAX get HSN code information
     */
    public function ajax_get_hsn_info() {
        check_ajax_referer('woohsn_nonce', 'nonce');
        
        $hsn_code = sanitize_text_field($_POST['hsn_code']);
        
        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT description, gst_rate FROM {$wpdb->prefix}woohsn_codes WHERE hsn_code = %s",
            $hsn_code
        ));
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error();
        }
    }
}