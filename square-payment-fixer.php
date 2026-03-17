<?php
/**
 * Plugin Name: Square Payment Method Fixer
 * Plugin URI: https://github.com/joelgratcyk/square-duplicate-payment-fixer/
 * Description: ONE-TIME cleanup tool for duplicate Square payment methods in Event Espresso. Activates on individual sites only. DELETE AFTER USE.
 * Version: 1.0.0
 * Author: Joel.Gr
 * Author URI: https://joel.gr
 * License: GPL v2 or later
 * Text Domain: square-payment-fixer
 * Domain Path: /languages
 * Network: false
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package SquarePaymentFixer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main class for Square Payment Method Fixer
 * 
 * This plugin is designed to be used ONCE on a specific subsite
 * to clean up duplicate Square payment method records.
 * 
 * IMPORTANT: Delete this plugin after use!
 */
class SquarePaymentFixer {
    
    /**
     * Option name to track if cleanup has been completed
     */
    private $option_name = 'square_payment_fixer_completed';
    
    /**
     * Constructor - hooks into WordPress
     */
    public function __construct() {
        // Only run on admin init
        add_action('admin_init', array($this, 'maybe_run_cleanup'));
        
        // Add admin notice to remind deletion
        add_action('admin_notices', array($this, 'reminder_notice'));
        
        // Add action links to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }
    
    /**
     * Check if cleanup should run and execute if needed
     */
    public function maybe_run_cleanup() {
        global $wpdb;
        
        // Check if we've already run on this site
        if (get_option($this->option_name)) {
            return;
        }
        
        // Security check - only allow admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Verify this is a one-time execution request
        if (!isset($_GET['square_fixer_run']) && !isset($_POST['square_fixer_run'])) {
            return;
        }
        
        // Verify nonce for security
        if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'square_fixer_run')) {
            wp_die('Security check failed');
        }
        
        // Run the cleanup
        $this->run_cleanup();
        
        // Mark as completed
        update_option($this->option_name, array(
            'timestamp' => current_time('mysql'),
            'site_id' => get_current_blog_id(),
            'site_url' => site_url()
        ));
    }
    
    /**
     * Execute the cleanup operations
     */
    private function run_cleanup() {
        global $wpdb;
        
        $results = array();
        $results['site_name'] = get_bloginfo('name');
        $results['site_id'] = get_current_blog_id();
        $results['site_url'] = site_url();
        $results['timestamp'] = current_time('mysql');
        
        // Find all Square payment methods
        $square_pms = $wpdb->get_results(
            "SELECT PMD_ID, PMD_slug, PMD_name FROM {$wpdb->prefix}esp_payment_method 
             WHERE PMD_slug LIKE '%square%'"
        );
        
        $results['found_count'] = count($square_pms);
        $results['actions'] = array();
        
        // Keep the first one, delete others
        if (count($square_pms) > 1) {
            $keep = array_shift($square_pms);
            $results['kept'] = array(
                'id' => $keep->PMD_ID,
                'slug' => $keep->PMD_slug,
                'name' => $keep->PMD_name
            );
            
            foreach ($square_pms as $pm) {
                $action = array(
                    'id' => $pm->PMD_ID,
                    'slug' => $pm->PMD_slug,
                    'name' => $pm->PMD_name
                );
                
                // Delete extra meta first
                $wpdb->delete(
                    $wpdb->prefix . 'esp_extra_meta',
                    array('EXM_type' => 'PaymentMethod', 'EXM_ID' => $pm->PMD_ID)
                );
                
                // Delete the payment method
                $wpdb->delete(
                    $wpdb->prefix . 'esp_payment_method',
                    array('PMD_ID' => $pm->PMD_ID)
                );
                
                $action['deleted'] = true;
                $results['actions'][] = $action;
            }
            
            // Clear Square-related options
            $wpdb->delete(
                $wpdb->options,
                array('option_name' => 'ee_payment_method_squareonsite_settings')
            );
            
            $results['options_cleared'] = true;
            
        } else {
            $results['no_duplicates'] = true;
        }
        
        // Store results for display
        set_transient('square_fixer_results', $results, 30);
        
        // Display results
        $this->display_results($results);
    }
    
    /**
     * Display cleanup results in admin
     */
    private function display_results($results) {
        ?>
        <div class="notice notice-success">
            <h3>✅ Square Payment Method Fixer - Cleanup Complete</h3>
            <table class="widefat striped" style="max-width: 800px; margin: 10px 0;">
                <tr>
                    <td><strong>Site:</strong></td>
                    <td><?php echo esc_html($results['site_name']); ?> (ID: <?php echo esc_html($results['site_id']); ?>)</td>
                </tr>
                <tr>
                    <td><strong>Site URL:</strong></td>
                    <td><?php echo esc_html($results['site_url']); ?></td>
                </tr>
                <tr>
                    <td><strong>Timestamp:</strong></td>
                    <td><?php echo esc_html($results['timestamp']); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment methods found:</strong></td>
                    <td><?php echo esc_html($results['found_count']); ?></td>
                </tr>
                <?php if (isset($results['kept'])): ?>
                <tr>
                    <td><strong>Preserved method:</strong></td>
                    <td><?php echo esc_html($results['kept']['name']); ?> (<?php echo esc_html($results['kept']['slug']); ?>)</td>
                </tr>
                <?php endif; ?>
            </table>
            
            <?php if (!empty($results['actions'])): ?>
                <h4>Removed Duplicates:</h4>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($results['actions'] as $action): ?>
                        <li><?php echo esc_html($action['name']); ?> (<?php echo esc_html($action['slug']); ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (isset($results['no_duplicates'])): ?>
                <p>ℹ️ No duplicates found. Your Square payment method configuration is fine.</p>
            <?php endif; ?>
            
            <p style="color: #d63638; font-weight: bold; margin-top: 15px;">
                ⚠️ IMPORTANT: This plugin has done its job. Please deactivate and delete it now!
            </p>
        </div>
        <?php
    }
    
    /**
     * Add reminder notice for plugin deletion
     */
    public function reminder_notice() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $completed = get_option($this->option_name);
        
        if ($completed) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>⚠️ Square Payment Method Fixer:</strong> This plugin has completed its task. 
                    <span style="color: #d63638; font-weight: bold;">Please deactivate and delete it now!</span>
                </p>
                <p>
                    <em>Cleanup completed: <?php echo esc_html($completed['timestamp']); ?> on site ID: <?php echo esc_html($completed['site_id']); ?></em>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add action links to plugins page
     */
    public function add_action_links($links) {
        $run_url = wp_nonce_url(
            add_query_arg('square_fixer_run', '1'),
            'square_fixer_run'
        );
        
        $run_link = '<a href="' . esc_url($run_url) . '" style="color: #d63638; font-weight: bold;">Run Cleanup</a>';
        array_unshift($links, $run_link);
        
        return $links;
    }
}

// Initialize the plugin
new SquarePaymentFixer();
