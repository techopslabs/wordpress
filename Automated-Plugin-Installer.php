<?php
/**
 * Plugin Name: Automated Plugin Installer
 * Description: Automatically installs and activates required plugins
 * Version: 1.0.0
 * Author: Sunil Kumar
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Automated_Plugin_Installer {
    
    // List of plugins to install (slug => name) - You can add more plugin here according to your need
    private $required_plugins = array(
        'wordpress-seo' => 'Yoast SEO',
        'wordfence' => 'Wordfence Security',
        'wp-super-cache' => 'WP Super Cache',
        'contact-form-7' => 'Contact Form 7',
        'advanced-custom-fields' => 'Advanced Custom Fields',
        'regenerate-thumbnails' => 'Regenerate Thumbnails'
    );
    
    /**
     * Initialize the class and set its hooks
     */
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Schedule a daily check to ensure plugins are installed
        if (!wp_next_scheduled('plugin_installer_daily_check')) {
            wp_schedule_event(time(), 'daily', 'plugin_installer_daily_check');
        }
        
        add_action('plugin_installer_daily_check', array($this, 'check_and_install_plugins'));
        
        // Also run on admin init, but not on every page load
        add_action('admin_init', array($this, 'admin_init_check'));
    }
    
    /**
     * Activation function
     */
    public function activate() {
        // Run the installer on activation
        $this->check_and_install_plugins();
    }
    
    /**
     * Check on admin init, but limit frequency
     */
    public function admin_init_check() {
        // Only run once every 24 hours
        $last_run = get_option('plugin_installer_last_run', 0);
        
        if (time() - $last_run > 86400) { // 24 hours in seconds
            $this->check_and_install_plugins();
            update_option('plugin_installer_last_run', time());
        }
    }
    
    /**
     * Check and install required plugins
     */
    public function check_and_install_plugins() {
        // Include necessary files for plugin installation
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
        
        // Use a silent upgrader skin
        class Silent_Upgrader_Skin extends WP_Upgrader_Skin {
            public function feedback($string, ...$args) {}
            public function header() {}
            public function footer() {}
            public function error($errors) {}
        }
        
        // Get installed plugins
        $installed_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        // Check each required plugin
        foreach ($this->required_plugins as $plugin_slug => $plugin_name) {
            $plugin_file = $this->get_plugin_file($plugin_slug, $installed_plugins);
            
            // If plugin is not installed, install it
            if (!$plugin_file) {
                $this->install_plugin($plugin_slug);
                $plugin_file = $this->get_plugin_file($plugin_slug, get_plugins());
            }
            
            // If plugin is installed but not active, activate it
            if ($plugin_file && !in_array($plugin_file, $active_plugins)) {
                activate_plugin($plugin_file);
            }
        }
    }
    
    /**
     * Get the plugin file based on slug
     */
    private function get_plugin_file($plugin_slug, $installed_plugins) {
        foreach ($installed_plugins as $path => $data) {
            if (strpos($path, $plugin_slug . '/') === 0) {
                return $path;
            }
        }
        return false;
    }
    
    /**
     * Install a plugin by slug
     */
    private function install_plugin($plugin_slug) {
        // Get plugin info from WordPress.org
        $api = plugins_api('plugin_information', array(
            'slug' => $plugin_slug,
            'fields' => array(
                'short_description' => false,
                'sections' => false,
                'requires' => false,
                'rating' => false,
                'ratings' => false,
                'downloaded' => false,
                'last_updated' => false,
                'added' => false,
                'tags' => false,
                'compatibility' => false,
                'homepage' => false,
                'donate_link' => false,
            ),
        ));
        
        if (is_wp_error($api)) {
            return false;
        }
        
        // Install the plugin
        $upgrader = new Plugin_Upgrader(new Silent_Upgrader_Skin());
        $install = $upgrader->install($api->download_link);
        
        return $install;
    }
}

// Initialize the class
new Automated_Plugin_Installer();
