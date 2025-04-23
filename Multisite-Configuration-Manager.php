<?php
/**
 * Plugin Name: Multisite Configuration Manager
 * Description: Centralized configuration for WordPress Multisite
 * Version: 1.0.0
 * Author: Sunil Kumar
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Multisite_Config_Manager {
    
    /**
     * Initialize the class and set its hooks
     */
    public function __construct() {
        // Only run on multisite
        if (!is_multisite()) {
            return;
        }
        
        // Set network-wide defaults for new sites
        add_action('wpmu_new_blog', array($this, 'setup_new_site'), 10, 6);
        
        // Restrict plugin installation on subsites
        add_filter('map_meta_cap', array($this, 'restrict_plugin_install'), 10, 4);
        
        // Add network admin notice about managed configuration
        add_action('network_admin_notices', array($this, 'network_admin_notice'));
        
        // Apply specific settings to all sites
        add_action('wp_initialize_site', array($this, 'initialize_all_sites'), 20);
        
        // Schedule daily sync of all sites
        if (!wp_next_scheduled('multisite_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'multisite_daily_sync');
        }
        
        add_action('multisite_daily_sync', array($this, 'sync_all_sites'));
    }
    
    /**
     * Set up a new site with default configurations
     */
    public function setup_new_site($blog_id, $user_id, $domain, $path, $site_id, $meta) {
        // Switch to the new blog
        switch_to_blog($blog_id);
        
        // Set default options
        $default_options = array(
            'blogdescription' => 'Another site in our awesome network',
            'permalink_structure' => '/%postname%/',
            'default_comment_status' => 'closed',
            'default_ping_status' => 'closed',
            'timezone_string' => 'America/New_York',
            'thumbnail_size_w' => 300,
            'thumbnail_size_h' => 300,
            'medium_size_w' => 600,
            'medium_size_h' => 600,
            'large_size_w' => 1200,
            'large_size_h' => 1200
        );
        
        foreach ($default_options as $option => $value) {
            update_option($option, $value);
        }
        
        // Create default pages
        $default_pages = array(
            'Home' => 'Welcome to our network site!',
            'About' => 'About this site',
            'Contact' => 'Contact us anytime',
            'Privacy Policy' => 'Our privacy commitments to you'
        );
        
        foreach ($default_pages as $title => $content) {
            // Create the page
            $page_id = wp_insert_post(array(
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            
            // Set the Home page as front page
            if ($title === 'Home') {
                update_option('page_on_front', $page_id);
                update_option('show_on_front', 'page');
            }
        }
        
        // Switch back to the previous blog
        restore_current_blog();
    }
    
    /**
     * Restrict plugin installation capabilities for subsites
     */
    public function restrict_plugin_install($caps, $cap, $user_id, $args) {
        // Check if we're trying to install plugins
        if (in_array($cap, array('install_plugins', 'activate_plugins', 'update_plugins', 'delete_plugins'))) {
            // Allow for super admins
            if (is_super_admin($user_id)) {
                return $caps;
            }
            
            // Restrict for everyone else
            $caps[] = 'do_not_allow';
        }
        
        return $caps;
    }
    
    /**
     * Display custom network admin notice
     */
    public function network_admin_notice() {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p>This network is managed by <strong>Multisite Configuration Manager</strong>. Site defaults and settings are automatically maintained.</p>';
        echo '</div>';
    }
    
    /**
     * Apply specific settings to all sites
     */
    public function initialize_all_sites() {
        // Get all sites
        $sites = get_sites();
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            // Apply settings that should be consistent across all sites
            update_option('default_ping_status', 'closed');
            update_option('default_comment_status', 'closed');
            
            // Disable comments on all existing posts
            global $wpdb;
            $wpdb->update($wpdb->posts, array('comment_status' => 'closed'), array('comment_status' => 'open'));
            
            restore_current_blog();
        }
    }
    
    /**
     * Sync settings across all sites
     */
    public function sync_all_sites() {
        // Get all sites
        $sites = get_sites();
        
        // Get main site options to sync
        switch_to_blog(1);
        $main_options = array(
            'thumbnail_size_w' => get_option('thumbnail_size_w'),
            'thumbnail_size_h' => get_option('thumbnail_size_h'),
            'medium_size_w' => get_option('medium_size_w'),
            'medium_size_h' => get_option('medium_size_h'),
            'large_size_w' => get_option('large_size_w'),
            'large_size_h' => get_option('large_size_h'),
            'timezone_string' => get_option('timezone_string')
        );
        restore_current_blog();
        
        // Apply to all sites except main site
        foreach ($sites as $site) {
            if ($site->blog_id == 1) {
                continue; // Skip main site
            }
            
            switch_to_blog($site->blog_id);
            
            foreach ($main_options as $option => $value) {
                update_option($option, $value);
            }
            
            restore_current_blog();
        }
    }
}

// Initialize the class
new Multisite_Config_Manager();
