/**
 * WooHSN Pro Frontend JavaScript
 */

jQuery(document).ready(function($) {
    
    // Tax calculator functionality
    if ($('.woohsn-pro-tax-calculator').length) {
        initTaxCalculator();
    }
    
    // HSN code tooltips
    initHsnTooltips();
    
    function initTaxCalculator() {
        $('.woohsn-pro-calculate-tax').on('click', function() {
            var productId = $(this).data('product-id');
            var quantity = parseInt($('.qty').val()) || 1;
            var price = parseFloat($(this).data('price')) || 0;
            
            if (!productId || !price) {
                return;
            }
            
            $.ajax({
                url: woohsn_pro_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woohsn_pro_calculate_tax',
                    product_id: productId,
                    quantity: quantity,
                    price: price,
                    nonce: woohsn_pro_ajax.nonce
                },
                beforeSend: function() {
                    $('.woohsn-pro-tax-results').html('<div class="woohsn-pro-loading">Calculating...</div>');
                },
                success: function(response) {
                    if (response.success) {
                        displayTaxResults(response.data);
                    } else {
                        $('.woohsn-pro-tax-results').html('<div class="error">Failed to calculate tax.</div>');
                    }
                }
            });
        });
        
        // Auto-calculate when quantity changes
        $('.qty').on('change', function() {
            $('.woohsn-pro-calculate-tax').trigger('click');
        });
    }
    
    function displayTaxResults(data) {
        var html = '<div class="woohsn-pro-tax-breakdown">';
        html += '<h4>Tax Breakdown</h4>';
        
        if (data.hsn_code) {
            html += '<div class="woohsn-pro-tax-row">';
            html += '<span class="woohsn-pro-tax-label">HSN Code:</span>';
            html += '<span class="woohsn-pro-tax-value">' + data.hsn_code + '</span>';
            html += '</div>';
        }
        
        if (data.gst_rate > 0) {
            html += '<div class="woohsn-pro-tax-row">';
            html += '<span class="woohsn-pro-tax-label">GST Rate:</span>';
            html += '<span class="woohsn-pro-tax-value">' + data.gst_rate + '%</span>';
            html += '</div>';
        }
        
        html += '<div class="woohsn-pro-tax-row">';
        html += '<span class="woohsn-pro-tax-label">Subtotal:</span>';
        html += '<span class="woohsn-pro-tax-value">' + data.subtotal + '</span>';
        html += '</div>';
        
        if (data.gst_rate > 0) {
            html += '<div class="woohsn-pro-tax-row">';
            html += '<span class="woohsn-pro-tax-label">GST Amount:</span>';
            html += '<span class="woohsn-pro-tax-value">' + data.gst_amount + '</span>';
            html += '</div>';
        }
        
        html += '<div class="woohsn-pro-tax-row">';
        html += '<span class="woohsn-pro-tax-label">Total:</span>';
        html += '<span class="woohsn-pro-tax-value">' + data.total + '</span>';
        html += '</div>';
        
        html += '</div>';
        
        $('.woohsn-pro-tax-results').html(html);
    }
    
    function initHsnTooltips() {
        $('.woohsn-pro-display').each(function() {
            var $this = $(this);
            var hsnCode = $this.text().match(/\d{4,8}/);
            
            if (hsnCode && typeof tippy !== 'undefined') {
                tippy(this, {
                    content: 'Loading HSN information...',
                    onShow: function(instance) {
                        // Load HSN info via AJAX
                        $.ajax({
                            url: woohsn_pro_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'woohsn_pro_get_hsn_info',
                                hsn_code: hsnCode[0],
                                nonce: woohsn_pro_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    var content = '<strong>HSN: ' + response.data.hsn_code + '</strong><br>';
                                    content += response.data.description + '<br>';
                                    content += '<small>GST Rate: ' + response.data.gst_rate + '%</small>';
                                    instance.setContent(content);
                                } else {
                                    instance.setContent('HSN information not available');
                                }
                            }
                        });
                    }
                });
            }
        });
    }
    
    // Animation for HSN display on load
    $('.woohsn-pro-display').addClass('woohsn-pro-fade-in');
    
    // Add fade-in CSS if not already present
    if (!$('#woohsn-pro-animations').length) {
        $('<style id="woohsn-pro-animations">')
            .text('.woohsn-pro-fade-in { animation: woohsnFadeIn 0.5s ease-in; } @keyframes woohsnFadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }')
            .appendTo('head');
    }
});