<?php
/**
 * AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize AJAX handlers
     */
    private function init_hooks() {
        // Public AJAX actions (for both logged in and not logged in users)
        $public_actions = array(
            'aap_create_order',
            'aap_capture_payment',
            'aap_validate_discount',
            'aap_check_report_status',
            'aap_get_preview',
        );
        
        foreach ($public_actions as $action) {
            add_action('wp_ajax_' . $action, array(__CLASS__, str_replace('aap_', 'handle_', $action)));
            add_action('wp_ajax_nopriv_' . $action, array(__CLASS__, str_replace('aap_', 'handle_', $action)));
        }
        
        // Private AJAX actions (logged in users only)
        $private_actions = array(
            'aap_admin_scan',
            'aap_rescan_report',
            'aap_delete_report',
            'aap_download_pdf',
            'aap_send_test_email',
            'aap_get_dashboard_stats',
        );
        
        foreach ($private_actions as $action) {
            add_action('wp_ajax_' . $action, array(__CLASS__, str_replace('aap_', 'handle_', $action)));
        }
    }
    
    /**
     * Create PayPal order
     */
    public static function handle_create_order() {
        check_ajax_referer('aap_frontend_nonce', 'nonce');
        
        $package_type = sanitize_text_field($_POST['package_type'] ?? '');
        $website_url = esc_url_raw($_POST['website_url'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        $discount_code = sanitize_text_field($_POST['discount_code'] ?? '');
        
        // Validate inputs
        if (empty($package_type) || empty($website_url) || empty($customer_email)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        }
        
        if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        if (!filter_var($website_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => 'Please enter a valid website URL.'));
        }
        
        // Check if admin free access
        if (AAP_PayPal::is_admin_free_access()) {
            $result = AAP_PayPal::create_admin_order($package_type, $website_url);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            wp_send_json_success(array(
                'admin_free' => true,
                'report_id' => $result['report_id'],
                'redirect_url' => home_url('/accessibility-audit/?view=status&id=' . $result['report_id']),
            ));
        }
        
        // Create PayPal order
        $return_url = home_url('/accessibility-audit/?payment=success');
        $cancel_url = home_url('/accessibility-audit/?payment=cancelled');
        
        $result = AAP_PayPal::create_order($package_type, $return_url, $cancel_url, array(
            'email' => $customer_email,
            'name' => $customer_name,
            'website_url' => $website_url,
            'discount_code' => $discount_code,
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'order_id' => $result['order_id'],
            'approval_url' => $result['approval_url'],
        ));
    }
    
    /**
     * Capture PayPal payment
     */
    public static function handle_capture_payment() {
        check_ajax_referer('aap_frontend_nonce', 'nonce');
        
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        
        if (empty($order_id)) {
            wp_send_json_error(array('message' => 'Invalid order ID.'));
        }
        
        $result = AAP_PayPal::capture_payment($order_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Get the report ID
        $transaction = AAP_Database::get_transaction_by_order_id($order_id);
        $report = $transaction ? AAP_Database::get_report_by_transaction($transaction['id']) : null;
        
        wp_send_json_success(array(
            'status' => 'completed',
            'report_id' => $report ? $report['id'] : null,
            'redirect_url' => $report 
                ? home_url('/accessibility-audit/?view=status&id=' . $report['id']) 
                : home_url('/accessibility-audit/?payment=success'),
        ));
    }
    
    /**
     * Validate discount code
     */
    public static function handle_validate_discount() {
        check_ajax_referer('aap_frontend_nonce', 'nonce');
        
        $code = sanitize_text_field($_POST['discount_code'] ?? '');
        $package_type = sanitize_text_field($_POST['package_type'] ?? '');
        
        if (empty($code)) {
            wp_send_json_error(array('message' => 'Please enter a discount code.'));
        }
        
        $result = AAP_PayPal::validate_discount_code($code);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Calculate new price
        $packages = AAP_Settings::get_packages();
        $original_price = floatval($packages[$package_type]['price'] ?? 0);
        
        if ($result['type'] === 'percentage') {
            $discount_amount = $original_price * ($result['amount'] / 100);
            $new_price = $original_price - $discount_amount;
            $discount_text = $result['amount'] . '% off';
        } else {
            $discount_amount = $result['amount'];
            $new_price = max(0, $original_price - $discount_amount);
            $discount_text = AAP_Settings::get_option('currency_symbol', '$') . number_format($result['amount'], 2) . ' off';
        }
        
        wp_send_json_success(array(
            'valid' => true,
            'discount_text' => $discount_text,
            'original_price' => number_format($original_price, 2),
            'discount_amount' => number_format($discount_amount, 2),
            'new_price' => number_format($new_price, 2),
        ));
    }
    
    /**
     * Check report status
     */
    public static function handle_check_report_status() {
        $report_id = intval($_POST['report_id'] ?? 0);
        $access_key = sanitize_text_field($_POST['access_key'] ?? '');
        
        if (!$report_id) {
            wp_send_json_error(array('message' => 'Invalid report ID.'));
        }
        
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(array('message' => 'Report not found.'));
        }
        
        // Verify access (either logged in admin or matching access key)
        $has_access = current_user_can('manage_options') || 
                      (!empty($access_key) && $report['access_key'] === $access_key);
        
        if (!$has_access) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }
        
        $response = array(
            'status' => $report['status'],
            'progress' => intval($report['progress'] ?? 0),
            'message' => self::get_status_message($report['status']),
        );
        
        if ($report['status'] === 'completed') {
            $response['download_url'] = AAP_PDF_Generator::get_download_url($report_id);
            
            $report_data = maybe_unserialize($report['report_data']);
            if ($report_data && isset($report_data['summary'])) {
                $response['summary'] = $report_data['summary'];
            }
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Get status message
     */
    private static function get_status_message($status) {
        $messages = array(
            'pending' => 'Your scan is queued and will start shortly...',
            'scanning' => 'Scanning your website for accessibility issues...',
            'processing' => 'Processing results and generating screenshots...',
            'generating' => 'Generating your PDF report...',
            'completed' => 'Your report is ready!',
            'failed' => 'There was an issue with your scan. Our team has been notified.',
        );
        
        return $messages[$status] ?? 'Processing...';
    }
    
    /**
     * Get preview scan (limited free scan)
     */
    public static function handle_get_preview() {
        check_ajax_referer('aap_frontend_nonce', 'nonce');
        
        $website_url = esc_url_raw($_POST['website_url'] ?? '');
        
        if (empty($website_url) || !filter_var($website_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => 'Please enter a valid website URL.'));
        }
        
        // Rate limiting
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        $transient_key = 'aap_preview_' . md5($ip);
        $preview_count = get_transient($transient_key) ?: 0;
        
        $max_previews = AAP_Settings::get_option('max_free_previews', 3);
        
        if ($preview_count >= $max_previews) {
            wp_send_json_error(array(
                'message' => 'You have reached the free preview limit. Please purchase an audit for full results.',
            ));
        }
        
        // Quick scan of just the homepage
        $scanner = new AAP_Scanner();
        $result = $scanner->scan_single_page($website_url, 'desktop');
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Increment preview count
        set_transient($transient_key, $preview_count + 1, DAY_IN_SECONDS);
        
        // Limit results for preview
        $issues = $result['issues'] ?? array();
        $limited_issues = array_slice($issues, 0, 5);
        $hidden_count = max(0, count($issues) - 5);
        
        wp_send_json_success(array(
            'url' => $website_url,
            'score' => $result['score'] ?? 0,
            'total_issues' => count($issues),
            'preview_issues' => $limited_issues,
            'hidden_count' => $hidden_count,
            'errors' => $result['summary']['errors'] ?? 0,
            'warnings' => $result['summary']['warnings'] ?? 0,
            'message' => $hidden_count > 0 
                ? "Showing 5 of {$hidden_count} issues. Purchase a full audit to see all results."
                : 'Preview complete.',
        ));
    }
    
    /**
     * Admin scan (free for admin users)
     */
    public static function handle_admin_scan() {
        check_ajax_referer('aap_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access.'));
        }
        
        $package_type = sanitize_text_field($_POST['package_type'] ?? '5_pages');
        $website_url = esc_url_raw($_POST['website_url'] ?? '');
        
        if (empty($website_url)) {
            wp_send_json_error(array('message' => 'Please enter a website URL.'));
        }
        
        $result = AAP_PayPal::create_admin_order($package_type, $website_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'report_id' => $result['report_id'],
            'message' => 'Scan started successfully. You will be notified when complete.',
            'view_url' => admin_url('admin.php?page=aap-reports&action=view&id=' . $result['report_id']),
        ));
    }
    
    /**
     * Rescan a report
     */
    public static function handle_rescan_report() {
        check_ajax_referer('aap_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access.'));
        }
        
        $report_id = intval($_POST['report_id'] ?? 0);
        
        if (!$report_id) {
            wp_send_json_error(array('message' => 'Invalid report ID.'));
        }
        
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(array('message' => 'Report not found.'));
        }
        
        // Reset report status
        AAP_Database::update_report($report_id, array(
            'status' => 'pending',
            'progress' => 0,
        ));
        
        // Delete old scanned pages
        AAP_Database::delete_scanned_pages($report_id);
        
        // Schedule new scan
        wp_schedule_single_event(time() + 5, 'aap_run_accessibility_scan', array($report_id));
        
        wp_send_json_success(array(
            'message' => 'Rescan scheduled. The report will be updated shortly.',
        ));
    }
    
    /**
     * Delete a report
     */
    public static function handle_delete_report() {
        check_ajax_referer('aap_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access.'));
        }
        
        $report_id = intval($_POST['report_id'] ?? 0);
        
        if (!$report_id) {
            wp_send_json_error(array('message' => 'Invalid report ID.'));
        }
        
        // Delete PDF if exists
        $report = AAP_Database::get_report($report_id);
        if ($report && !empty($report['pdf_path']) && file_exists($report['pdf_path'])) {
            unlink($report['pdf_path']);
        }
        
        // Delete screenshots
        $pages = AAP_Database::get_scanned_pages($report_id);
        foreach ($pages as $page) {
            if (!empty($page['screenshot_path']) && file_exists($page['screenshot_path'])) {
                unlink($page['screenshot_path']);
            }
        }
        
        // Delete from database
        AAP_Database::delete_report($report_id);
        
        wp_send_json_success(array(
            'message' => 'Report deleted successfully.',
        ));
    }
    
    /**
     * Download PDF
     */
    public static function handle_download_pdf() {
        $report_id = intval($_GET['report_id'] ?? $_POST['report_id'] ?? 0);
        $nonce = sanitize_text_field($_GET['nonce'] ?? $_POST['nonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, 'aap_download_pdf_' . $report_id)) {
            wp_die('Invalid security token.');
        }
        
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            wp_die('Report not found.');
        }
        
        // Generate PDF if it doesn't exist
        if (empty($report['pdf_path']) || !file_exists($report['pdf_path'])) {
            $pdf_path = AAP_PDF_Generator::generate($report_id);
            
            if (!$pdf_path) {
                wp_die('Failed to generate PDF.');
            }
        } else {
            $pdf_path = $report['pdf_path'];
        }
        
        // Serve the PDF
        $filename = 'accessibility-report-' . sanitize_file_name(parse_url($report['website_url'], PHP_URL_HOST)) . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdf_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        readfile($pdf_path);
        exit;
    }
    
    /**
     * Send test email
     */
    public static function handle_send_test_email() {
        check_ajax_referer('aap_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access.'));
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        $result = AAP_Email::send_test_email($email);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Test email sent successfully to ' . $email));
        } else {
            wp_send_json_error(array('message' => 'Failed to send test email. Please check your email configuration.'));
        }
    }
    
    /**
     * Get dashboard stats
     */
    public static function handle_get_dashboard_stats() {
        check_ajax_referer('aap_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access.'));
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '30');
        $since = date('Y-m-d H:i:s', strtotime("-{$period} days"));
        
        $stats = array(
            'total_reports' => AAP_Database::count_reports_since($since),
            'completed_reports' => AAP_Database::count_reports_since($since, 'completed'),
            'failed_reports' => AAP_Database::count_reports_since($since, 'failed'),
            'total_revenue' => AAP_Database::sum_transactions_since($since),
            'average_score' => AAP_Database::average_score_since($since),
            'popular_packages' => AAP_Database::get_popular_packages($since),
            'recent_reports' => AAP_Database::get_recent_reports(5),
        );
        
        wp_send_json_success($stats);
    }
}

// Initialize AJAX handlers
AAP_Ajax::init();
