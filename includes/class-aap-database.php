<?php
/**
 * Database Operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Database {
    
    /**
     * Create a new report
     */
    public static function create_report($data) {
        global $wpdb;
        
        $report_id = self::generate_report_id();
        
        $insert_data = array(
            'report_id' => $report_id,
            'user_id' => get_current_user_id(),
            'customer_email' => sanitize_email($data['email']),
            'customer_name' => sanitize_text_field($data['name'] ?? ''),
            'website_url' => esc_url_raw($data['url']),
            'package_type' => sanitize_text_field($data['package']),
            'pages_count' => intval($data['pages_count']),
            'payment_status' => $data['payment_status'] ?? 'pending',
            'payment_amount' => floatval($data['amount'] ?? 0),
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'aap_reports',
            $insert_data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%f')
        );
        
        return $report_id;
    }
    
    /**
     * Get report by ID
     */
    public static function get_report($report_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aap_reports WHERE report_id = %s",
                $report_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Update report
     */
    public static function update_report($report_id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'aap_reports',
            $data,
            array('report_id' => $report_id)
        );
    }
    
    /**
     * Get reports list
     */
    public static function get_reports($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'user_id' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'scan_status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $query = "SELECT * FROM {$wpdb->prefix}aap_reports WHERE {$where_clause} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Save scanned page
     */
    public static function save_scanned_page($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'aap_scanned_pages',
            array(
                'report_id' => $data['report_id'],
                'page_url' => $data['page_url'],
                'page_title' => $data['page_title'] ?? '',
                'device_type' => $data['device_type'],
                'screenshot_path' => $data['screenshot_path'] ?? '',
                'issues_count' => intval($data['issues_count'] ?? 0),
                'errors_count' => intval($data['errors_count'] ?? 0),
                'warnings_count' => intval($data['warnings_count'] ?? 0),
                'passed_count' => intval($data['passed_count'] ?? 0),
                'scan_data' => maybe_serialize($data['scan_data'] ?? array()),
            )
        );
    }
    
    /**
     * Get scanned pages for report
     */
    public static function get_scanned_pages($report_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aap_scanned_pages WHERE report_id = %s ORDER BY id ASC",
                $report_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Save transaction
     */
    public static function save_transaction($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'aap_transactions',
            array(
                'report_id' => $data['report_id'],
                'transaction_id' => $data['transaction_id'],
                'payer_email' => $data['payer_email'] ?? '',
                'amount' => floatval($data['amount']),
                'currency' => $data['currency'] ?? 'USD',
                'status' => $data['status'] ?? 'completed',
                'payment_method' => $data['payment_method'] ?? 'paypal',
                'raw_response' => maybe_serialize($data['raw_response'] ?? array()),
            )
        );
    }
    
    /**
     * Generate unique report ID
     */
    private static function generate_report_id() {
        return 'AAP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
    }
    
    /**
     * Delete report and associated data
     */
    public static function delete_report($report_id) {
        global $wpdb;
        
        // Get report to delete files
        $report = self::get_report($report_id);
        
        if ($report) {
            // Delete PDF file
            if (!empty($report['pdf_path']) && file_exists($report['pdf_path'])) {
                unlink($report['pdf_path']);
            }
            
            // Delete screenshots
            $pages = self::get_scanned_pages($report_id);
            foreach ($pages as $page) {
                if (!empty($page['screenshot_path']) && file_exists($page['screenshot_path'])) {
                    unlink($page['screenshot_path']);
                }
            }
        }
        
        // Delete from database
        $wpdb->delete($wpdb->prefix . 'aap_reports', array('report_id' => $report_id));
        $wpdb->delete($wpdb->prefix . 'aap_scanned_pages', array('report_id' => $report_id));
        $wpdb->delete($wpdb->prefix . 'aap_transactions', array('report_id' => $report_id));
        
        return true;
    }
    
    /**
     * Get statistics
     */
    public static function get_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total reports
        $stats['total_reports'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aap_reports"
        );
        
        // Completed reports
        $stats['completed_reports'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aap_reports WHERE scan_status = 'completed'"
        );
        
        // Total revenue
        $stats['total_revenue'] = $wpdb->get_var(
            "SELECT SUM(amount) FROM {$wpdb->prefix}aap_transactions WHERE status = 'completed'"
        ) ?? 0;
        
        // This month revenue
        $stats['month_revenue'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$wpdb->prefix}aap_transactions 
                WHERE status = 'completed' AND MONTH(created_at) = %d AND YEAR(created_at) = %d",
                date('n'),
                date('Y')
            )
        ) ?? 0;
        
        // Total pages scanned
        $stats['total_pages_scanned'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aap_scanned_pages"
        );
        
        return $stats;
    }
}
