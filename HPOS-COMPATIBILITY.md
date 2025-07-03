# WooHSN - High-Performance Order Storage (HPOS) Compatibility

## Overview

WooHSN now supports WooCommerce's High-Performance Order Storage (HPOS), also known as Custom Order Tables. This ensures optimal performance and future compatibility with WooCommerce's storage architecture.

## What is HPOS?

High-Performance Order Storage (HPOS) is WooCommerce's solution for storing order data in custom database tables instead of the traditional WordPress posts system. This provides:

- **Better Performance**: Faster order queries and reduced database load
- **Improved Scalability**: Handle large volumes of orders more efficiently  
- **Enhanced Reliability**: Dedicated tables for order data with proper indexing
- **Future-Proof**: The standard for new WooCommerce installations (v8.2+)

## HPOS Features in WooHSN

### 1. Automatic Compatibility Declaration
The plugin automatically declares compatibility with HPOS using WooCommerce's feature compatibility system.

### 2. Dual Storage Support
WooHSN works seamlessly with both:
- **HPOS Mode**: Uses custom order tables for order data
- **Legacy Mode**: Uses traditional WordPress posts/postmeta tables
- **Sync Mode**: Maintains data in both systems during transition

### 3. Order Management
- Order HSN summary calculation works with both storage methods
- Order meta data (HSN calculations, GST totals) stored using HPOS-compatible methods
- Admin order views show HSN information regardless of storage mode

### 4. Admin Features
- **HPOS Status Indicator**: Shows current storage mode in admin
- **Order Columns**: GST amount column in orders list (both HPOS and legacy)
- **Meta Boxes**: HSN summary meta box in order edit screens
- **Bulk Operations**: All bulk HSN operations work with both storage modes

## Technical Implementation

### Classes Added
- `WooHSN_HPOS_Compatibility`: Core compatibility layer
- `WooHSN_Order`: HPOS-compatible order management

### Key Methods
- `WooHSN_HPOS_Compatibility::is_hpos_enabled()`: Check if HPOS is active
- `WooHSN_HPOS_Compatibility::get_order()`: Get order object safely
- `WooHSN_HPOS_Compatibility::update_order_meta()`: Update order meta data
- `WooHSN_HPOS_Compatibility::get_orders()`: Query orders with compatibility

### Order Data Storage
Order-related HSN data is stored as:
- `_woohsn_summary`: Complete HSN breakdown per order
- `_woohsn_total_gst`: Total GST amount for the order
- `_woohsn_calculated_at`: Calculation timestamp

## Migration and Setup

### For New Installations
HPOS support is automatic - no additional setup required.

### For Existing Sites
1. The plugin will work with your current setup (legacy or HPOS)
2. If you enable HPOS later, all functionality continues to work
3. Admin notices will inform you of your current HPOS status

### Enabling HPOS in WooCommerce
1. Go to **WooCommerce > Settings > Advanced > Features**
2. Enable **"High-performance order storage"**
3. Enable **"Enable compatibility mode"** during transition
4. Allow synchronization to complete

## Development Guidelines

### For Plugin Developers
When extending WooHSN's order functionality:

```php
// Always use the compatibility layer
$order = WooHSN_HPOS_Compatibility::get_order($order_id);

// Update order meta safely
WooHSN_HPOS_Compatibility::update_order_meta($order_id, $meta_key, $meta_value);

// Query orders
$orders = WooHSN_HPOS_Compatibility::get_orders($args);
```

### Hooks and Filters
All existing WooHSN hooks continue to work:
- `woohsn_before_order_calculation`
- `woohsn_after_order_calculation`
- `woohsn_order_hsn_summary`

## Troubleshooting

### Common Issues

**Q: Order HSN data not showing after enabling HPOS?**
A: Enable compatibility mode temporarily to sync data between storage systems.

**Q: Performance not improved after enabling HPOS?**
A: Ensure you have sufficient orders (1000+) to see meaningful performance gains.

**Q: Plugin showing HPOS warnings?**
A: Update to the latest WooHSN version - older versions may not have HPOS support.

### Debug Information
Enable WordPress debug mode to see HPOS status information:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

HPOS information will be logged to help with troubleshooting.

## Performance Benefits

With HPOS enabled, you can expect:
- **5x faster** order creation
- **40x faster** customer order queries
- **10x faster** order meta searches
- **3x faster** general order operations

*Performance improvements vary based on order volume and server configuration.*

## Version Compatibility

- **WooCommerce**: 5.0+ (HPOS available from WC 7.1+)
- **WordPress**: 5.0+
- **PHP**: 7.4+

## Support

For HPOS-related issues:
1. Check your WooCommerce HPOS settings
2. Verify WooHSN admin notices for status information
3. Enable debug logging to review HPOS compatibility status
4. Contact support with HPOS status information

---

**Note**: HPOS is the future of WooCommerce order storage. We recommend enabling it for all new stores and migrating existing stores when ready.