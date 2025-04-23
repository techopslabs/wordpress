<?php
/**
 * Plugin Name: Theme Enforcer
 * Description: Forces a specific theme to always be active
 * Version: 1.0.0
 * Author: Sunil Kumar
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Theme_Enforcer {
    
    // The theme that should always be active
    private $enforced_theme = 'your-custom-theme';
    
    /**
     * Initialize the class and set its hooks
     */
    public function __construct() {
        // Force the theme to be active
        add_filter('pre_option_template', array($this, 'force_theme'));
        add_filter('pre_option_stylesheet', array($this, 'force_theme'));
        
        // Prevent theme switching
        add_action('setup_theme', array($this, 'block_theme_switch'));
        
        // Hide theme selection in Customizer
        add_action('customize_register', array($this, 'remove_theme_selector'), 20);
        
        // Hide themes page
        add_action('admin_menu', array($this, 'remove_appearance_submenu'));
    }
    
    /**
     * Force the specified theme to be active
     */
    public function force_theme() {
        return $this->enforced_theme;
    }
    
    /**
     * Block theme switching
     */
    public function block_theme_switch() {
        if (isset($_GET['action']) && $_GET['action'] == 'activate' && 
            isset($_GET['stylesheet']) && $_GET['stylesheet'] != $this->enforced_theme) {
            wp_redirect(admin_url('themes.php'));
            exit;
        }
    }
    
    /**
     * Remove theme selection in Customizer
     */
    public function remove_theme_selector($wp_customize) {
        $wp_customize->remove_section('themes');
        $wp_customize->remove_control('active_theme');
    }
    
    /**
     * Remove themes submenu from appearance
     */
    public function remove_appearance_submenu() {
        remove_submenu_page('themes.php', 'themes.php');
    }
}

// Initialize the class
new Theme_Enforcer();
