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
 * Main Plugin Class
 */
final class Accessibility_Audit_Pro {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-activator.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-deactivator.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-database.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-settings.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-scanner.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-screenshot.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-pdf-generator.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-paypal.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-email.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-ajax.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-shortcodes.php';
        require_once AAP_PLUGIN_DIR . 'includes/class-aap-api.php';
        
        // Admin
        if (is_admin()) {
            require_once AAP_PLUGIN_DIR . 'admin/class-aap-admin.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array('AAP_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('AAP_Deactivator', 'deactivate'));
        
        // Init
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register custom post type for reports
        $this->register_post_types();
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'accessibility-audit-pro',
            false,
            dirname(AAP_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Initialize components
     */
    public function init_components() {
        AAP_Settings::get_instance();
        AAP_Ajax::get_instance();
        AAP_Shortcodes::get_instance();
        AAP_API::get_instance();
        
        if (is_admin()) {
            AAP_Admin::get_instance();
        }
    }
    
    /**
     * Register custom post types
     */
    private function register_post_types() {
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
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
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
        
        wp_localize_script('aap-frontend', 'aap_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aap_nonce'),
            'paypal_client_id' => AAP_Settings::get_option('paypal_client_id'),
            'paypal_mode' => AAP_Settings::get_option('paypal_mode', 'sandbox'),
            'currency' => AAP_Settings::get_option('currency', 'USD'),
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
    public function enqueue_admin_assets($hook) {
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
     * Check if current user is admin/superuser (free access)
     */
    public static function is_admin_user() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $admin_roles = AAP_Settings::get_option('free_access_roles', array('administrator'));
        $user = wp_get_current_user();
        
        return array_intersect($admin_roles, $user->roles) ? true : false;
    }
}

/**
 * Initialize plugin
 */
function accessibility_audit_pro() {
    return Accessibility_Audit_Pro::get_instance();
}

// Start the plugin
accessibility_audit_pro();
