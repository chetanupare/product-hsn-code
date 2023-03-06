jQuery( document ).ready( function( $ ) {
    // Retrieve the product HSN code color from the data attribute
    var productHSNCodeColor = $( '.product-hsn-code-color' ).data( 'product-hsn-code-color' );

    // Initialize color picker with custom palette and default color
    $( '.product-hsn-code-color' ).spectrum({
        color: productHSNCodeColor,
        showPalette: true,
        palette: [
            ['#000000', '#ffffff', '#ff0000', '#00ff00', '#0000ff'],
            ['#ff00ff', '#ffff00', '#00ffff', '#c0c0c0', '#808080'],
            ['#800000', '#808000', '#008000', '#008080', '#000080'],
            ['#ff8c00', '#ff69b4', '#9400d3', '#8b0000', '#228b22']
        ],
        showInput: true,
        showInitial: true,
        preferredFormat: 'hex'
    });
} );
jQuery(document).ready(function($) {
    var previewDiv = $('#product-hsn-code-style-preview');
    var select = $('#product-hsn-code-style');
    var defaultStyle = '<?php echo esc_attr( get_option( "product_hsn_code_style", "default" ) ); ?>';
    previewDiv.css('font-style', defaultStyle);
    select.change(function() {
        var style = $(this).val();
        previewDiv.css('font-style', style);
    });
});

jQuery(document).ready(function($) {
  // handle click events of the tabs
  $('.nav-tab-wrapper a').click(function(event) {
    event.preventDefault();
    // switch active tab
    $('.nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
    // switch active content
    $('.product-hsn-code-tab').hide();
    $($(this).attr('href')).show();
  });

  // show style preview on style change
  $('#product-hsn-code-style').change(function() {
    var style = $(this).val();
    $('#product-hsn-code-style-preview').css('font-style', style);
  });
});
