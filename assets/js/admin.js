/**
 * WooHSN Pro Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Initialize color picker
    if ($.fn.wpColorPicker) {
        $('.woohsn-pro-color-picker').wpColorPicker();
    }
    
    // Tab navigation
    $('.woohsn-pro-nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).data('target');
        
        // Update active tab
        $('.woohsn-pro-nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('.woohsn-pro-tab-content').hide();
        $('#' + target).show();
    });
    
    // DataTable initialization
    if ($('#woohsn-pro-hsn-table').length) {
        $('#woohsn-pro-hsn-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: woohsn_pro_ajax.ajax_url,
                type: 'POST',
                data: function(d) {
                    d.action = 'woohsn_pro_get_hsn_codes';
                    d.nonce = woohsn_pro_ajax.nonce;
                }
            },
            columns: [
                { data: 'hsn_code', title: 'HSN Code' },
                { data: 'description', title: 'Description' },
                { data: 'gst_rate', title: 'GST Rate (%)' },
                { data: 'created_at', title: 'Created' },
                { data: 'actions', title: 'Actions', orderable: false }
            ],
            order: [[0, 'asc']],
            pageLength: 25,
            responsive: true
        });
    }
    
    // HSN code search
    $('#woohsn-pro-hsn-search').on('input', function() {
        var searchTerm = $(this).val();
        
        if (searchTerm.length >= 2) {
            $.ajax({
                url: woohsn_pro_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woohsn_pro_search_hsn',
                    search_term: searchTerm,
                    nonce: woohsn_pro_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayHsnSuggestions(response.data);
                    }
                }
            });
        } else {
            $('#woohsn-pro-suggestions').hide();
        }
    });
    
    // Display HSN suggestions
    function displayHsnSuggestions(suggestions) {
        var html = '';
        
        suggestions.forEach(function(item) {
            html += '<div class="woohsn-pro-suggestion" data-hsn="' + item.hsn_code + '">';
            html += '<strong>' + item.hsn_code + '</strong> - ' + item.description;
            if (item.gst_rate) {
                html += ' <span class="gst-rate">(GST: ' + item.gst_rate + '%)</span>';
            }
            html += '</div>';
        });
        
        $('#woohsn-pro-suggestions-list').html(html);
        $('#woohsn-pro-suggestions').show();
    }
    
    // Handle suggestion clicks
    $(document).on('click', '.woohsn-pro-suggestion', function() {
        var hsnCode = $(this).data('hsn');
        $('#woohsn-pro-hsn-search').val(hsnCode);
        $('#woohsn-pro-suggestions').hide();
    });
    
    // Bulk operations
    $('#woohsn-pro-bulk-assign-btn').on('click', function() {
        var productIds = [];
        var hsnCode = $('#woohsn-pro-bulk-hsn-code').val();
        
        $('input[name="product_ids[]"]:checked').each(function() {
            productIds.push($(this).val());
        });
        
        if (productIds.length === 0) {
            alert('Please select at least one product.');
            return;
        }
        
        if (!hsnCode) {
            alert('Please enter an HSN code.');
            return;
        }
        
        $.ajax({
            url: woohsn_pro_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'woohsn_pro_bulk_assign',
                product_ids: productIds,
                hsn_code: hsnCode,
                nonce: woohsn_pro_ajax.nonce
            },
            beforeSend: function() {
                $('#woohsn-pro-bulk-assign-btn').prop('disabled', true).text('Processing...');
            },
            success: function(response) {
                if (response.success) {
                    alert(woohsn_pro_ajax.strings.bulk_assign_success);
                    location.reload();
                } else {
                    alert(response.data || woohsn_pro_ajax.strings.bulk_assign_error);
                }
            },
            complete: function() {
                $('#woohsn-pro-bulk-assign-btn').prop('disabled', false).text('Assign HSN Codes');
            }
        });
    });
    
    // Import/Export handlers
    $('#woohsn-pro-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'woohsn_pro_import_csv');
        formData.append('nonce', woohsn_pro_ajax.nonce);
        
        $.ajax({
            url: woohsn_pro_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#woohsn-pro-import-btn').prop('disabled', true).text('Importing...');
                $('#woohsn-pro-import-progress').show();
            },
            success: function(response) {
                if (response.success) {
                    showImportResults(response.data);
                } else {
                    alert('Import failed: ' + response.data);
                }
            },
            complete: function() {
                $('#woohsn-pro-import-btn').prop('disabled', false).text('Import CSV');
                $('#woohsn-pro-import-progress').hide();
            }
        });
    });
    
    // Export handler
    $('.woohsn-pro-export-btn').on('click', function() {
        var exportType = $(this).data('type');
        var includeEmpty = $('#include-empty-products').is(':checked');
        
        $.ajax({
            url: woohsn_pro_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'woohsn_pro_export_csv',
                export_type: exportType,
                include_empty: includeEmpty,
                nonce: woohsn_pro_ajax.nonce
            },
            beforeSend: function() {
                $('.woohsn-pro-export-btn').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.download_url;
                } else {
                    alert('Export failed: ' + response.data);
                }
            },
            complete: function() {
                $('.woohsn-pro-export-btn').prop('disabled', false);
            }
        });
    });
    
    // Show import results
    function showImportResults(data) {
        var html = '<div class="woohsn-pro-import-results">';
        html += '<h4>Import Results</h4>';
        html += '<p><strong>Total processed:</strong> ' + data.total + '</p>';
        html += '<p><strong>Successful:</strong> ' + data.success + '</p>';
        html += '<p><strong>Errors:</strong> ' + data.errors + '</p>';
        
        if (data.messages && data.messages.length > 0) {
            html += '<h5>Messages:</h5><ul>';
            data.messages.forEach(function(message) {
                html += '<li>' + message + '</li>';
            });
            html += '</ul>';
        }
        
        html += '</div>';
        
        $('#woohsn-pro-import-results').html(html).show();
    }
    
    // HSN code form validation
    $('#woohsn-pro-hsn-form').on('submit', function(e) {
        var hsnCode = $('#hsn_code').val();
        var description = $('#description').val();
        var gstRate = $('#gst_rate').val();
        
        if (!hsnCode || !description || gstRate === '') {
            e.preventDefault();
            alert('Please fill in all required fields.');
            return false;
        }
        
        if (!/^[0-9]{4,8}$/.test(hsnCode)) {
            e.preventDefault();
            alert('HSN code must be 4-8 digits.');
            return false;
        }
        
        if (isNaN(gstRate) || gstRate < 0 || gstRate > 100) {
            e.preventDefault();
            alert('GST rate must be a number between 0 and 100.');
            return false;
        }
    });
    
    // Delete HSN code
    $(document).on('click', '.woohsn-pro-delete-hsn', function() {
        if (!confirm(woohsn_pro_ajax.strings.confirm_delete)) {
            return;
        }
        
        var id = $(this).data('id');
        
        $.ajax({
            url: woohsn_pro_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'woohsn_pro_delete_hsn_code',
                id: id,
                nonce: woohsn_pro_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#woohsn-pro-hsn-table').DataTable().ajax.reload();
                } else {
                    alert('Delete failed: ' + response.data);
                }
            }
        });
    });
    
    // Settings form auto-save
    $('.woohsn-pro-setting').on('change', function() {
        var setting = $(this).attr('name');
        var value = $(this).val();
        
        if ($(this).is(':checkbox')) {
            value = $(this).is(':checked') ? 'yes' : 'no';
        }
        
        $.ajax({
            url: woohsn_pro_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'woohsn_pro_save_setting',
                setting: setting,
                value: value,
                nonce: woohsn_pro_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSaveIndicator();
                }
            }
        });
    });
    
    // Show save indicator
    function showSaveIndicator() {
        var indicator = $('<span class="woohsn-pro-saved">✓ Saved</span>');
        indicator.insertAfter('.woohsn-pro-setting:focus').fadeOut(2000, function() {
            $(this).remove();
        });
    }
    
    // Preview HSN display
    $('#woohsn-pro-preview-btn').on('click', function() {
        var format = $('#woohsn_pro_display_format').val() || 'HSN Code: {code}';
        var color = $('#woohsn_pro_color').val() || '#333333';
        var fontSize = $('#woohsn_pro_font_size').val() || '14';
        var fontWeight = $('#woohsn_pro_font_weight').val() || 'normal';
        var backgroundColor = $('#woohsn_pro_background_color').val() || '#f8f9fa';
        var borderColor = $('#woohsn_pro_border_color').val() || '#dee2e6';
        
        var previewHtml = format.replace('{code}', '6403');
        var styles = {
            'color': color,
            'font-size': fontSize + 'px',
            'font-weight': fontWeight,
            'background-color': backgroundColor,
            'border': '1px solid ' + borderColor,
            'padding': '8px 12px',
            'border-radius': '4px',
            'display': 'inline-block'
        };
        
        $('#woohsn-pro-display-preview').html(previewHtml).css(styles).show();
    });
});