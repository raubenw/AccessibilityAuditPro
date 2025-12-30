<?php
/**
 * Plugin Name: Accessibility Audit Pro
 * Plugin URI: https://openwebaccess.com/plugins/accessibility-audit-pro
 * Description: Professional WCAG accessibility auditing with multi-device testing, PDF reports, and PayPal payments.
 * Version: 1.0.0
 * Author: Open Web Access
 * Author URI: https://openwebaccess.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: accessibility-audit-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AAP_VERSION', '1.0.0');
define('AAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Activation hook - runs before any classes are loaded
 */
register_activation_hook(__FILE__, 'aap_activate_plugin');

function aap_activate_plugin() {
    // Load only what's needed for activation
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-activator.php';
    AAP_Activator::activate();
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'aap_deactivate_plugin');

function aap_deactivate_plugin() {
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-deactivator.php';
    AAP_Deactivator::deactivate();
}

/**
 * Initialize plugin after WordPress is fully loaded
 */
add_action('plugins_loaded', 'aap_init_plugin');

function aap_init_plugin() {
    // Load text domain
    load_plugin_textdomain(
        'accessibility-audit-pro',
        false,
        dirname(AAP_PLUGIN_BASENAME) . '/languages'
    );
    
    // Load core classes
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-database.php';
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-settings.php';
    
    // Initialize settings
    AAP_Settings::get_instance();
}

/**
 * Load frontend classes
 */
add_action('init', 'aap_init_frontend');

function aap_init_frontend() {
    // Load classes needed for frontend
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-scanner.php';
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-screenshot.php';
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-paypal.php';
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-email.php';
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-ajax.php';
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-shortcodes.php';
    require_once AAP_PLUGIN_DIR . 'includes/class-aap-api.php';
    
    // Initialize components
    AAP_Ajax::get_instance();
    AAP_Shortcodes::get_instance();
    AAP_API::get_instance();
    
    // Register post type
    register_post_type('aap_report', array(
        'labels' => array(
            'name' => __('Accessibility Reports', 'accessibility-audit-pro'),
            'singular_name' => __('Accessibility Report', 'accessibility-audit-pro'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'capability_type' => 'post',
        'supports' => array('title'),
        'rewrite' => false,
    ));
}

/**
 * Load admin classes
 */
add_action('admin_init', 'aap_init_admin');

function aap_init_admin() {
    if (!is_admin()) {
        return;
    }
    
    require_once AAP_PLUGIN_DIR . 'admin/class-aap-admin.php';
    AAP_Admin::get_instance();
}

/**
 * Admin menu - needs to run on admin_menu hook
 */
add_action('admin_menu', 'aap_add_admin_menu');

function aap_add_admin_menu() {
    // Load admin class if not already loaded
    if (!class_exists('AAP_Admin')) {
        require_once AAP_PLUGIN_DIR . 'admin/class-aap-admin.php';
    }
    
    // Main menu
    add_menu_page(
        __('Accessibility Audit', 'accessibility-audit-pro'),
        __('Accessibility Audit', 'accessibility-audit-pro'),
        'manage_options',
        'accessibility-audit-pro',
        array('AAP_Admin', 'render_dashboard'),
        'dashicons-universal-access-alt',
        30
    );
    
    // Dashboard submenu
    add_submenu_page(
        'accessibility-audit-pro',
        __('Dashboard', 'accessibility-audit-pro'),
        __('Dashboard', 'accessibility-audit-pro'),
        'manage_options',
        'accessibility-audit-pro',
        array('AAP_Admin', 'render_dashboard')
    );
    
    // Reports submenu
    add_submenu_page(
        'accessibility-audit-pro',
        __('Reports', 'accessibility-audit-pro'),
        __('Reports', 'accessibility-audit-pro'),
        'manage_options',
        'accessibility-audit-reports',
        array('AAP_Admin', 'render_reports')
    );
    
    // New Scan submenu
    add_submenu_page(
        'accessibility-audit-pro',
        __('New Scan', 'accessibility-audit-pro'),
        __('New Scan', 'accessibility-audit-pro'),
        'manage_options',
        'accessibility-audit-new',
        array('AAP_Admin', 'render_new_scan')
    );
    
    // Settings submenu
    add_submenu_page(
        'accessibility-audit-pro',
        __('Settings', 'accessibility-audit-pro'),
        __('Settings', 'accessibility-audit-pro'),
        'manage_options',
        'accessibility-audit-settings',
        array('AAP_Admin', 'render_settings')
    );
}

/**
 * Enqueue frontend assets
 */
add_action('wp_enqueue_scripts', 'aap_enqueue_frontend_assets');

function aap_enqueue_frontend_assets() {
    wp_enqueue_style(
        'aap-frontend',
        AAP_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        AAP_VERSION
    );
    
    wp_enqueue_script(
        'aap-frontend',
        AAP_PLUGIN_URL . 'assets/js/frontend.js',
        array('jquery'),
        AAP_VERSION,
        true
    );
    
    // Get PayPal settings safely
    $paypal_client_id = '';
    $paypal_mode = 'sandbox';
    $currency = 'USD';
    
    if (class_exists('AAP_Settings')) {
        $paypal_client_id = AAP_Settings::get_option('paypal_client_id', '');
        $paypal_mode = AAP_Settings::get_option('paypal_mode', 'sandbox');
        $currency = AAP_Settings::get_option('currency', 'USD');
    }
    
    wp_localize_script('aap-frontend', 'aap_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aap_nonce'),
        'paypal_client_id' => $paypal_client_id,
        'paypal_mode' => $paypal_mode,
        'currency' => $currency,
        'strings' => array(
            'scanning' => __('Scanning website...', 'accessibility-audit-pro'),
            'generating' => __('Generating report...', 'accessibility-audit-pro'),
            'error' => __('An error occurred. Please try again.', 'accessibility-audit-pro'),
            'success' => __('Report generated successfully!', 'accessibility-audit-pro'),
        ),
    ));
}

/**
 * Enqueue admin assets
 */
add_action('admin_enqueue_scripts', 'aap_enqueue_admin_assets');

function aap_enqueue_admin_assets($hook) {
    if (strpos($hook, 'accessibility-audit') === false) {
        return;
    }
    
    wp_enqueue_style(
        'aap-admin',
        AAP_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        AAP_VERSION
    );
    
    wp_enqueue_script(
        'aap-admin',
        AAP_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery', 'wp-color-picker'),
        AAP_VERSION,
        true
    );
    
    wp_enqueue_style('wp-color-picker');
    
    wp_localize_script('aap-admin', 'aap_admin_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aap_admin_nonce'),
    ));
}

/**
 * Check if current user has free admin access
 */
function aap_is_admin_user() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    $admin_roles = array('administrator');
    if (class_exists('AAP_Settings')) {
        $admin_roles = AAP_Settings::get_option('free_access_roles', array('administrator'));
    }
    
    $user = wp_get_current_user();
    return array_intersect($admin_roles, $user->roles) ? true : false;
}
