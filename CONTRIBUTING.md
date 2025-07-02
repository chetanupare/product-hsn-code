# Contributing to WooHSN Pro

Thank you for your interest in contributing to WooHSN Pro! This document provides guidelines and information for contributors.

## 🤝 How to Contribute

### Reporting Bugs

1. **Check existing issues** - Search through [existing issues](https://github.com/chetanupare/woohsn-pro/issues) first
2. **Create a detailed bug report** - Include:
   - WordPress version
   - WooCommerce version
   - PHP version
   - Plugin version
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots/error logs if applicable

### Suggesting Features

1. **Check discussions** - Look through [GitHub Discussions](https://github.com/chetanupare/woohsn-pro/discussions)
2. **Create a feature request** - Include:
   - Clear description of the feature
   - Use case and benefits
   - Possible implementation approach
   - Any relevant examples

### Code Contributions

#### Development Setup

1. **Fork the repository**
   ```bash
   git clone https://github.com/YOUR-USERNAME/woohsn-pro.git
   cd woohsn-pro
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install  # if using npm for frontend assets
   ```

3. **Create a branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bug-fix
   ```

#### Coding Standards

- **Follow WordPress Coding Standards** - Use PHPCS with WordPress rules
- **Comment your code** - Especially complex logic
- **Write secure code** - Sanitize inputs, escape outputs, use nonces
- **Test your changes** - Ensure compatibility with different WordPress/WooCommerce versions

#### Code Style

```bash
# Check code standards
composer run phpcs

# Fix code standards automatically
composer run phpcbf

# Run tests (if available)
composer run test
```

#### Pull Request Process

1. **Update documentation** - Update README.md if needed
2. **Test thoroughly** - Test on different environments
3. **Commit with clear messages**
   ```
   feat: add HSN code auto-suggestion feature
   fix: resolve tax calculation rounding issue
   docs: update installation instructions
   ```

4. **Create pull request** with:
   - Clear title and description
   - Reference to related issues
   - Screenshots for UI changes
   - Testing instructions

## 📋 Development Guidelines

### File Structure

```
woohsn-pro/
├── woohsn-pro.php              # Main plugin file
├── includes/                   # Core classes
├── assets/                     # CSS, JS, images
├── templates/                  # Admin templates
├── languages/                  # Translation files
└── tests/                      # Unit tests (if applicable)
```

### Naming Conventions

- **Classes**: `WooHSN_Pro_Class_Name`
- **Functions**: `woohsn_pro_function_name()`
- **Variables**: `$variable_name`
- **Constants**: `WOOHSN_PRO_CONSTANT`
- **Hooks**: `woohsn_pro_hook_name`

### Security Guidelines

- **Sanitize all inputs** using appropriate WordPress functions
- **Escape all outputs** using `esc_html()`, `esc_attr()`, etc.
- **Use nonces** for all forms and AJAX requests
- **Check capabilities** before sensitive operations
- **Use prepared statements** for database queries

### Database Guidelines

- **Use WordPress database API** (`$wpdb`)
- **Create proper indexes** for performance
- **Handle database errors** gracefully
- **Use transactions** for critical operations

## 🧪 Testing

### Manual Testing

1. **Test on fresh WordPress installation**
2. **Test with different themes**
3. **Test with common plugins**
4. **Test on different PHP versions**
5. **Test import/export functionality**
6. **Test with large datasets**

### Automated Testing (Future)

- Unit tests with PHPUnit
- Integration tests
- Code coverage reports

## 📝 Documentation

### Code Documentation

- **Use PHPDoc** for all functions and classes
- **Include examples** in docblocks
- **Document parameters** and return values
- **Explain complex logic**

### User Documentation

- Update README.md for new features
- Add screenshots for UI changes
- Update installation/configuration steps
- Include troubleshooting information

## 🐛 Debugging

### Enable Debug Mode

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Plugin Debug Functions

```php
// Log debug information
woohsn_pro_log_activity('Debug message', 'debug');

// Check plugin version
$version = woohsn_pro_get_version();
```

## 🚀 Release Process

### Version Numbering

- **Major**: `x.0.0` - Breaking changes
- **Minor**: `1.x.0` - New features, backward compatible
- **Patch**: `1.1.x` - Bug fixes, backward compatible

### Release Checklist

- [ ] Update version numbers
- [ ] Update changelog
- [ ] Test on multiple environments
- [ ] Update documentation
- [ ] Create release notes
- [ ] Tag release in Git

## 💬 Communication

### Getting Help

- **GitHub Issues** - For bugs and feature requests
- **GitHub Discussions** - For questions and general discussion
- **WordPress.org Support** - For user support

### Code Review

- Be respectful and constructive
- Focus on the code, not the person
- Provide specific feedback
- Ask questions if unclear
- Suggest improvements

## 📜 License

By contributing to WooHSN Pro, you agree that your contributions will be licensed under the GPL v2 or later license.

## 🙏 Recognition

Contributors will be recognized in:
- CHANGELOG.md
- README.md credits section
- Plugin about page (for significant contributions)

---

**Thank you for contributing to WooHSN Pro!** 🎉

Your contributions help make HSN code management better for the entire WordPress community.