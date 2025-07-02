<?php
/**
 * Admin Dashboard Template for WooHSN Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get statistics
$database = new WooHSN_Database();
$stats = $database->get_hsn_statistics();
?>

<div class="wrap woohsn-pro-admin">
    <div class="woohsn-admin-header">
        <h1><?php esc_html_e('WooHSN Dashboard', 'woohsn'); ?></h1>
        <p><?php esc_html_e('Smart HSN tagging system for WooCommerce stores. Automate GST readiness with minimal effort.', 'woohsn'); ?></p>
    </div>
    
    <div class="woohsn-pro-dashboard-stats">
        <div class="woohsn-pro-card">
            <h3><?php _e('Overview', 'woohsn-pro'); ?></h3>
            <div class="woohsn-pro-stats">
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['total_hsn_codes']); ?></h3>
                    <p><?php _e('HSN Codes', 'woohsn-pro'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['products_with_hsn']); ?></h3>
                    <p><?php _e('Products with HSN', 'woohsn-pro'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['products_without_hsn']); ?></h3>
                    <p><?php _e('Products without HSN', 'woohsn-pro'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['completion_percentage']); ?>%</h3>
                    <p><?php _e('Completion Rate', 'woohsn-pro'); ?></p>
                </div>
            </div>
            
            <div class="woohsn-pro-progress-container">
                <div class="woohsn-pro-progress-bar" style="width: <?php echo esc_attr($stats['completion_percentage']); ?>%">
                    <?php echo esc_html($stats['completion_percentage']); ?>% Complete
                </div>
            </div>
        </div>
    </div>
    
    <div class="woohsn-pro-dashboard-actions">
        <div class="woohsn-pro-card">
            <h3><?php _e('Quick Actions', 'woohsn-pro'); ?></h3>
            <div class="woohsn-pro-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=woohsn-pro-bulk'); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Bulk Assign HSN Codes', 'woohsn-pro'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=woohsn-pro-database'); ?>" class="button button-large">
                    <span class="dashicons dashicons-database"></span>
                    <?php _e('Manage HSN Database', 'woohsn-pro'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=woohsn-pro-reports'); ?>" class="button button-large">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php _e('View Reports', 'woohsn-pro'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=woohsn-pro-settings'); ?>" class="button button-large">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Settings', 'woohsn-pro'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <?php if (!empty($stats['gst_rate_breakdown'])): ?>
    <div class="woohsn-pro-gst-breakdown">
        <div class="woohsn-pro-card">
            <h3><?php _e('GST Rate Breakdown', 'woohsn-pro'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('GST Rate', 'woohsn-pro'); ?></th>
                        <th><?php _e('Number of HSN Codes', 'woohsn-pro'); ?></th>
                        <th><?php _e('Percentage', 'woohsn-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['gst_rate_breakdown'] as $breakdown): ?>
                    <tr>
                        <td><?php echo esc_html($breakdown['rate']); ?>%</td>
                        <td><?php echo esc_html($breakdown['count']); ?></td>
                        <td><?php echo esc_html(round(($breakdown['count'] / $stats['total_hsn_codes']) * 100, 1)); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="woohsn-pro-recent-activity">
        <div class="woohsn-pro-card">
            <h3><?php _e('Recent Activity (Last 7 Days)', 'woohsn-pro'); ?></h3>
            <div class="woohsn-pro-stats">
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['recent_imports']); ?></h3>
                    <p><?php _e('Imports', 'woohsn-pro'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['recent_exports']); ?></h3>
                    <p><?php _e('Exports', 'woohsn-pro'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="woohsn-pro-help">
        <div class="woohsn-pro-card">
            <h3><?php _e('Need Help?', 'woohsn-pro'); ?></h3>
            <p><?php _e('Check out our documentation and support resources:', 'woohsn-pro'); ?></p>
            <ul>
                <li><a href="#" target="_blank"><?php _e('Plugin Documentation', 'woohsn-pro'); ?></a></li>
                <li><a href="#" target="_blank"><?php _e('Video Tutorials', 'woohsn-pro'); ?></a></li>
                <li><a href="#" target="_blank"><?php _e('Support Forum', 'woohsn-pro'); ?></a></li>
                <li><a href="#" target="_blank"><?php _e('Contact Support', 'woohsn-pro'); ?></a></li>
            </ul>
        </div>
    </div>
</div>