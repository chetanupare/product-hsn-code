# WooHSN - Smart HSN Tagging System for WooCommerce

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://wordpress.org/plugins/woohsn/)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)
[![Author](https://img.shields.io/badge/author-Chetan%20Upare-orange.svg)](https://github.com/chetanupare)

## 🚀 Overview

WooHSN is a comprehensive WordPress plugin that provides smart HSN (Harmonized System of Nomenclature) code management for WooCommerce stores. Automate GST readiness with minimal effort and simplify tax compliance for Indian businesses.

## ✨ Key Features

### 🎯 Core Functionality
- **Easy HSN Code Assignment** - Add HSN codes to products effortlessly through an intuitive interface
- **Product Integration** - Seamless integration with WooCommerce products
- **GST Rate Management** - Automatically calculate and display GST rates based on HSN codes
- **Bulk Operations** - Assign HSN codes to multiple products at once using CSV import/export
- **Smart Suggestions** - Get intelligent HSN code suggestions based on product titles and descriptions

### 📊 Data Management
- **Database Management** - Comprehensive HSN code database with descriptions and GST rates
- **Import/Export** - CSV-based bulk import and export functionality
- **Data Validation** - Built-in validation and format checking
- **Performance Optimized** - Caching and optimized queries for better performance

### 🎨 Customization
- **Frontend Display** - Show HSN codes on product pages, cart, and order details
- **Customizable Display** - Style HSN code display to match your theme
- **Multiple Positions** - Choose where to display HSN codes on your store
- **Custom GST Rates** - Product-specific GST rate overrides when needed

### 📈 Reports & Analytics
- **Dashboard Overview** - Quick statistics and completion rates
- **HSN Analytics** - Product-wise HSN code assignment tracking
- **Export Capabilities** - Generate reports for accounting software
- **GST Breakdown** - Detailed GST rate analysis

## 🛠 Installation

1. **Upload** the plugin files to `/wp-content/plugins/woohsn/` directory, or install through WordPress admin
2. **Activate** the plugin through the 'Plugins' screen in WordPress
3. **Go to WooHSN > Settings** to configure display options
4. **Start adding HSN codes** to your products!

## ⚙️ Configuration

### Initial Setup
1. Navigate to **WooHSN > Settings**
2. Configure display options and styling preferences
3. Choose where to show HSN codes (product pages, cart, orders)
4. Set up GST rate display preferences

### Product Configuration
1. Edit any WooCommerce product
2. Find the "HSN Code Information" meta box
3. Enter HSN code or use the smart suggestion feature
4. Optionally enable custom GST rates for specific products

## 📁 File Structure

```
woohsn/
├── woohsn.php                  # Main plugin file
├── readme.txt                 # WordPress.org readme
├── includes/                   # Core functionality
│   ├── class-woohsn-admin.php
│   ├── class-woohsn-frontend.php
│   ├── class-woohsn-product.php
│   ├── class-woohsn-import-export.php
│   ├── class-woohsn-tax-calculator.php
│   ├── class-woohsn-database.php
│   └── functions.php
├── assets/                     # Static assets
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   └── images/
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
$hsn_code = woohsn_get_product_hsn_code($product_id);
$gst_rate = woohsn_get_gst_rate($hsn_code);
```

### Calculate Tax for Product
```php
$tax_data = woohsn_calculate_product_gst($product_id, $price, $quantity);
```

## 🎨 Customization

### Styling HSN Display
The plugin provides complete CSS control:

```css
.woohsn-display {
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
- `woohsn_before_display` - Before HSN code display
- `woohsn_after_display` - After HSN code display
- `woohsn_import_complete` - After successful import

### Filters
- `woohsn_display_format` - Modify display format
- `woohsn_gst_rate` - Override GST rate calculation
- `woohsn_hsn_suggestions` - Customize HSN suggestions

## 📊 Database Schema

### HSN Codes Table
```sql
CREATE TABLE wp_woohsn_codes (
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
CREATE TABLE wp_woohsn_logs (
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

## 🔄 Migration Support

WooHSN automatically handles data migration when upgrading:

1. **Data Preservation** - All existing HSN codes are preserved
2. **Setting Migration** - Previous settings automatically migrated
3. **Meta Key Update** - Product meta keys updated to new format
4. **Clean Migration** - No data loss during updates

## 🐛 Troubleshooting

### Common Issues

**HSN codes not displaying:**
- Check display settings in WooHSN > Settings
- Verify HSN codes are assigned to products
- Clear cache if using caching plugins

**Import failing:**
- Ensure CSV format matches template
- Check file permissions and size limits
- Verify proper column headers

**Performance issues:**
- Clear plugin cache and optimize database
- Check for theme/plugin conflicts
- Ensure WooCommerce is up to date

## 📝 Changelog

### Version 1.0.0 (Current)
- ✅ Initial WordPress.org release
- ✅ HSN code assignment for products
- ✅ Bulk import/export functionality
- ✅ Smart HSN code suggestions
- ✅ GST rate management
- ✅ Frontend display options
- ✅ Comprehensive admin dashboard
- ✅ Reports and analytics
- ✅ WordPress.org compliance

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

- **Documentation:** Available in plugin dashboard
- **Support Forum:** [WordPress.org Support](https://wordpress.org/support/plugin/woohsn/)
- **Bug Reports:** [GitHub Issues](https://github.com/chetanupare/woohsn/issues)
- **Feature Requests:** [GitHub Discussions](https://github.com/chetanupare/woohsn/discussions)

## 🏆 Credits

- **Author & Developer:** [Chetan Upare](https://github.com/chetanupare)
- **HSN Database:** Government of India Classification
- **Icons:** WordPress Dashicons
- **Testing:** WordPress & WooCommerce Community

---

**Made with ❤️ for Indian businesses using WordPress & WooCommerce**