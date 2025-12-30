<?php
/**
 * Plugin Activator
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Create upload directories
        self::create_directories();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron jobs
        self::schedule_cron();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Reports table
        $table_reports = $wpdb->prefix . 'aap_reports';
        $sql_reports = "CREATE TABLE $table_reports (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_id varchar(64) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            customer_email varchar(255) NOT NULL,
            customer_name varchar(255) DEFAULT '',
            website_url varchar(500) NOT NULL,
            package_type varchar(50) NOT NULL,
            pages_count int(11) NOT NULL DEFAULT 5,
            payment_status varchar(50) DEFAULT 'pending',
            payment_id varchar(255) DEFAULT '',
            payment_amount decimal(10,2) DEFAULT 0.00,
            scan_status varchar(50) DEFAULT 'pending',
            report_data longtext,
            pdf_path varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY user_id (user_id),
            KEY payment_status (payment_status),
            KEY scan_status (scan_status)
        ) $charset_collate;";
        
        // Scanned pages table
        $table_pages = $wpdb->prefix . 'aap_scanned_pages';
        $sql_pages = "CREATE TABLE $table_pages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_id varchar(64) NOT NULL,
            page_url varchar(500) NOT NULL,
            page_title varchar(500) DEFAULT '',
            device_type varchar(50) NOT NULL,
            screenshot_path varchar(500) DEFAULT '',
            issues_count int(11) DEFAULT 0,
            errors_count int(11) DEFAULT 0,
            warnings_count int(11) DEFAULT 0,
            passed_count int(11) DEFAULT 0,
            scan_data longtext,
            scanned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY device_type (device_type)
        ) $charset_collate;";
        
        // Transactions table
        $table_transactions = $wpdb->prefix . 'aap_transactions';
        $sql_transactions = "CREATE TABLE $table_transactions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_id varchar(64) NOT NULL,
            transaction_id varchar(255) NOT NULL,
            payer_email varchar(255) DEFAULT '',
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'USD',
            status varchar(50) DEFAULT 'pending',
            payment_method varchar(50) DEFAULT 'paypal',
            raw_response longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY transaction_id (transaction_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reports);
        dbDelta($sql_pages);
        dbDelta($sql_transactions);
        
        // Store DB version
        update_option('aap_db_version', AAP_VERSION);
    }
    
    /**
     * Create necessary directories
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/accessibility-audit-pro';
        
        $directories = array(
            $base_dir,
            $base_dir . '/reports',
            $base_dir . '/screenshots',
            $base_dir . '/temp',
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            
            // Add index.php for security
            $index_file = $dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
        }
        
        // Add .htaccess to protect files
        $htaccess = $base_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files \"*.pdf\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents($htaccess, $htaccess_content);
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = array(
            // General
            'company_name' => get_bloginfo('name'),
            'company_logo' => '',
            'company_email' => get_option('admin_email'),
            
            // Pricing
            'price_5_pages' => '29.00',
            'price_10_pages' => '49.00',
            'price_25_pages' => '99.00',
            'price_50_pages' => '179.00',
            'price_100_pages' => '299.00',
            'currency' => 'USD',
            
            // PayPal
            'paypal_mode' => 'sandbox',
            'paypal_client_id' => '',
            'paypal_client_secret' => '',
            
            // Email
            'email_from_name' => get_bloginfo('name'),
            'email_from_address' => get_option('admin_email'),
            'email_subject' => 'Your Website Accessibility Audit Report',
            
            // Access
            'free_access_roles' => array('administrator'),
            
            // API Keys (for external services)
            'screenshot_api_key' => '',
            'screenshot_api_provider' => 'internal',
            
            // Branding
            'report_header_color' => '#07599c',
            'report_accent_color' => '#09e1c0',
            'show_powered_by' => true,
            
            // Limits
            'max_concurrent_scans' => 3,
            'scan_timeout' => 30,
            'rate_limit_per_hour' => 10,
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('aap_' . $key) === false) {
                update_option('aap_' . $key, $value);
            }
        }
    }
    
    /**
     * Schedule cron jobs
     */
    private static function schedule_cron() {
        if (!wp_next_scheduled('aap_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'aap_cleanup_temp_files');
        }
        
        if (!wp_next_scheduled('aap_process_pending_scans')) {
            wp_schedule_event(time(), 'hourly', 'aap_process_pending_scans');
        }
    }
}
