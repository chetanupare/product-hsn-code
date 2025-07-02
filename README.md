# WooHSN Pro - Professional HSN Code Management for WooCommerce

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/woohsn-pro/woohsn-pro)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)

## 🚀 Overview

WooHSN Pro is a comprehensive WordPress plugin that provides professional HSN (Harmonized System of Nomenclature) code management for WooCommerce stores. Perfect for businesses dealing with GST compliance, tax calculations, and international trade requirements.

## ✨ Key Features

### 🎯 Core Functionality
- **Advanced HSN Code Management** - Complete database of HSN codes with descriptions and GST rates
- **Product Integration** - Seamless integration with WooCommerce products
- **Automated Tax Calculation** - Real-time GST calculations based on HSN codes
- **Bulk Operations** - Efficiently assign HSN codes to multiple products
- **Smart Suggestions** - AI-powered HSN code suggestions based on product titles

### 📊 Data Management
- **Import/Export** - CSV-based bulk import and export functionality
- **Database Backup** - Automated backup and restore capabilities
- **Data Validation** - Comprehensive validation and error handling
- **Caching System** - Optimized performance with intelligent caching

### 🎨 Customization
- **Display Options** - Flexible HSN code display on product pages
- **Styling Controls** - Complete control over appearance and positioning
- **Custom GST Rates** - Product-specific GST rate overrides
- **Multiple Display Locations** - Show HSN codes on shop, cart, and order pages

### 📈 Reporting & Analytics
- **GST Reports** - Detailed GST breakdown and analysis
- **HSN Analytics** - Product-wise HSN code statistics
- **Export Reports** - Generate reports in multiple formats
- **Dashboard Widgets** - Quick overview of HSN code completion rates

## 🛠 Installation

1. **Download** the plugin files
2. **Upload** to your WordPress installation's `/wp-content/plugins/` directory
3. **Activate** the plugin through the 'Plugins' menu in WordPress
4. **Configure** settings via the WooHSN Pro menu in admin

## ⚙️ Configuration

### Initial Setup
1. Navigate to **WooHSN Pro > Settings**
2. Configure display options and styling
3. Import HSN database (included sample data)
4. Set up tax calculation preferences

### Product Configuration
1. Edit any WooCommerce product
2. Find the "HSN Code Information" meta box
3. Enter HSN code or use the suggestion feature
4. Optionally set custom GST rates

## 📁 File Structure

```
woohsn-pro/
├── woohsn-pro.php              # Main plugin file
├── includes/                   # Core functionality
│   ├── class-woohsn-pro-admin.php
│   ├── class-woohsn-pro-frontend.php
│   ├── class-woohsn-pro-product.php
│   ├── class-woohsn-pro-import-export.php
│   ├── class-woohsn-pro-tax-calculator.php
│   ├── class-woohsn-pro-database.php
│   └── functions.php
├── assets/                     # Static assets
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── templates/                  # Admin templates
│   └── admin-dashboard.php
└── languages/                  # Translation files
```

## 🔧 Technical Requirements

- **WordPress:** 5.0 or higher
- **WooCommerce:** 5.0 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher

## 📋 Usage Examples

### Display HSN Code with Shortcode
```php
[woohsn_code product_id="123" format="HSN: {code}" show_gst="yes"]
```

### Get HSN Code Programmatically
```php
$hsn_code = woohsn_pro_get_product_hsn_code($product_id);
$gst_rate = woohsn_pro_get_gst_rate($hsn_code);
```

### Calculate Tax for Product
```php
$tax_data = woohsn_pro_calculate_product_gst($product_id, $price, $quantity);
```

## 🎨 Customization

### Styling HSN Display
The plugin provides complete CSS control:

```css
.woohsn-pro-display {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 8px 12px;
    border-radius: 4px;
}
```

### Custom Display Formats
Configure display format in settings:
- `HSN Code: {code}`
- `HSN: {code} (GST: {rate}%)`
- `Product Code: {code}`

## 🔌 Hooks & Filters

### Actions
- `woohsn_pro_before_display` - Before HSN code display
- `woohsn_pro_after_display` - After HSN code display
- `woohsn_pro_import_complete` - After successful import

### Filters
- `woohsn_pro_display_format` - Modify display format
- `woohsn_pro_gst_rate` - Override GST rate calculation
- `woohsn_pro_hsn_suggestions` - Customize HSN suggestions

## 📊 Database Schema

### HSN Codes Table
```sql
CREATE TABLE wp_woohsn_pro_codes (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    hsn_code varchar(20) NOT NULL,
    description text,
    gst_rate decimal(5,2) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY hsn_code (hsn_code)
);
```

### Logs Table
```sql
CREATE TABLE wp_woohsn_pro_logs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    operation_type varchar(20) NOT NULL,
    file_name varchar(255),
    records_processed int DEFAULT 0,
    success_count int DEFAULT 0,
    error_count int DEFAULT 0,
    user_id bigint(20) UNSIGNED,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

## 🛡️ Security Features

- **Nonce Verification** - All AJAX requests protected
- **Capability Checks** - Proper user permission validation
- **Data Sanitization** - Input sanitization and validation
- **SQL Injection Protection** - Prepared statements throughout
- **File Upload Security** - Secure file handling and validation

## 🚀 Performance Optimizations

- **Intelligent Caching** - Transient-based caching system
- **Database Optimization** - Optimized queries and indexing
- **Lazy Loading** - On-demand resource loading
- **Minified Assets** - Compressed CSS and JavaScript
- **CDN Ready** - Compatible with content delivery networks

## 🔄 Migration from Old Plugin

The new WooHSN Pro automatically handles migration from the previous version:

1. **Data Preservation** - All existing HSN codes are preserved
2. **Setting Migration** - Previous settings automatically migrated
3. **Meta Key Update** - Product meta keys updated to new format
4. **Backup Creation** - Automatic backup before migration

## 🐛 Troubleshooting

### Common Issues

**HSN codes not displaying:**
- Check display settings in WooHSN Pro > Settings
- Verify HSN codes are assigned to products
- Clear cache if using caching plugins

**Import failing:**
- Ensure CSV format matches template
- Check file size limits (5MB max)
- Verify proper column headers

**Performance issues:**
- Enable caching in settings
- Optimize database tables via dashboard
- Check for plugin conflicts

## 📝 Changelog

### Version 2.0.0 (Current)
- ✅ Complete plugin redesign and rewrite
- ✅ Enhanced security and performance
- ✅ Advanced tax calculation engine
- ✅ Improved user interface
- ✅ Comprehensive reporting system
- ✅ Better WooCommerce integration

### Version 1.0.0 (Legacy)
- Basic HSN code functionality
- Simple import/export
- Basic styling options

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation:** [Plugin Documentation](https://woohsnpro.com/docs/)
- **Support Forum:** [WordPress.org Support](https://wordpress.org/support/plugin/woohsn-pro/)
- **Premium Support:** [Contact Us](https://woohsnpro.com/support/)
- **Bug Reports:** [GitHub Issues](https://github.com/woohsn-pro/woohsn-pro/issues)

## 🏆 Credits

- **Development Team:** WooHSN Pro Development Team
- **HSN Database:** Government of India Classification
- **Icons:** WordPress Dashicons
- **Testing:** WooCommerce Community

---

**Made with ❤️ for the WordPress & WooCommerce community**