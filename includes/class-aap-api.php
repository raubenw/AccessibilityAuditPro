<?php
/**
 * REST API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_API {
    
    private static $instance = null;
    
    /**
     * API Namespace
     */
    const NAMESPACE = 'accessibility-audit/v1';
    
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
     * Initialize API
     */
    private function init_hooks() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Public routes
        register_rest_route(self::NAMESPACE, '/preview', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'preview_scan'),
            'permission_callback' => '__return_true',
            'args' => array(
                'url' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return filter_var($param, FILTER_VALIDATE_URL);
                    },
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/packages', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_packages'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route(self::NAMESPACE, '/reports/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_report'),
            'permission_callback' => array(__CLASS__, 'check_report_access'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/reports/(?P<id>\d+)/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_report_status'),
            'permission_callback' => array(__CLASS__, 'check_report_access'),
        ));
        
        // Admin only routes
        register_rest_route(self::NAMESPACE, '/reports', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'list_reports'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'type' => 'integer',
                ),
                'per_page' => array(
                    'default' => 20,
                    'type' => 'integer',
                ),
                'status' => array(
                    'type' => 'string',
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/reports', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'create_report'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'url' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'package' => array(
                    'default' => '5_pages',
                    'type' => 'string',
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/reports/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'delete_report'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        register_rest_route(self::NAMESPACE, '/reports/(?P<id>\d+)/rescan', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'rescan_report'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        register_rest_route(self::NAMESPACE, '/statistics', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_statistics'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'period' => array(
                    'default' => '30',
                    'type' => 'string',
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/transactions', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'list_transactions'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'type' => 'integer',
                ),
                'per_page' => array(
                    'default' => 20,
                    'type' => 'integer',
                ),
            ),
        ));
        
        // Webhook endpoints
        register_rest_route(self::NAMESPACE, '/webhooks/paypal', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'handle_paypal_webhook'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Check admin permission
     */
    public static function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Check report access permission
     */
    public static function check_report_access($request) {
        // Admins always have access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check for access key in header or query param
        $access_key = $request->get_header('X-Access-Key');
        if (empty($access_key)) {
            $access_key = $request->get_param('access_key');
        }
        
        if (empty($access_key)) {
            return false;
        }
        
        $report_id = $request->get_param('id');
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return false;
        }
        
        return $report['access_key'] === $access_key;
    }
    
    /**
     * Preview scan endpoint
     */
    public static function preview_scan($request) {
        $url = $request->get_param('url');
        
        // Rate limiting
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        $transient_key = 'aap_api_preview_' . md5($ip);
        $count = get_transient($transient_key) ?: 0;
        
        if ($count >= 10) { // 10 API previews per day
            return new WP_Error(
                'rate_limited',
                'API rate limit exceeded. Please try again tomorrow.',
                array('status' => 429)
            );
        }
        
        $scanner = new AAP_Scanner();
        $result = $scanner->scan_single_page($url, 'desktop');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        set_transient($transient_key, $count + 1, DAY_IN_SECONDS);
        
        // Limit preview results
        $issues = $result['issues'] ?? array();
        
        return rest_ensure_response(array(
            'url' => $url,
            'score' => $result['score'] ?? 0,
            'total_issues' => count($issues),
            'issues' => array_slice($issues, 0, 5),
            'summary' => array(
                'errors' => $result['summary']['errors'] ?? 0,
                'warnings' => $result['summary']['warnings'] ?? 0,
            ),
        ));
    }
    
    /**
     * Get packages endpoint
     */
    public static function get_packages() {
        $packages = AAP_Settings::get_packages();
        $currency = AAP_Settings::get_option('currency', 'USD');
        $currency_symbol = AAP_Settings::get_option('currency_symbol', '$');
        
        $response = array(
            'currency' => $currency,
            'currency_symbol' => $currency_symbol,
            'packages' => array(),
        );
        
        foreach ($packages as $key => $package) {
            $response['packages'][$key] = array(
                'id' => $key,
                'name' => $package['name'],
                'pages' => $package['pages'],
                'price' => floatval($package['price']),
                'formatted_price' => $currency_symbol . number_format($package['price'], 2),
            );
        }
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get single report
     */
    public static function get_report($request) {
        $report_id = $request->get_param('id');
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return new WP_Error('not_found', 'Report not found', array('status' => 404));
        }
        
        $report_data = maybe_unserialize($report['report_data']);
        $pages = AAP_Database::get_scanned_pages($report_id);
        
        return rest_ensure_response(array(
            'id' => $report['id'],
            'website_url' => $report['website_url'],
            'status' => $report['status'],
            'package_type' => $report['package_type'],
            'created_at' => $report['created_at'],
            'completed_at' => $report['completed_at'],
            'summary' => $report_data['summary'] ?? null,
            'wcag_compliance' => $report_data['wcag_compliance'] ?? null,
            'pages_count' => count($pages),
            'pdf_url' => $report['status'] === 'completed' ? AAP_PDF_Generator::get_download_url($report_id) : null,
        ));
    }
    
    /**
     * Get report status
     */
    public static function get_report_status($request) {
        $report_id = $request->get_param('id');
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return new WP_Error('not_found', 'Report not found', array('status' => 404));
        }
        
        $response = array(
            'status' => $report['status'],
            'progress' => intval($report['progress'] ?? 0),
        );
        
        if ($report['status'] === 'completed') {
            $report_data = maybe_unserialize($report['report_data']);
            $response['summary'] = $report_data['summary'] ?? null;
            $response['pdf_url'] = AAP_PDF_Generator::get_download_url($report_id);
        }
        
        return rest_ensure_response($response);
    }
    
    /**
     * List reports (admin only)
     */
    public static function list_reports($request) {
        $page = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100);
        $status = $request->get_param('status');
        
        $args = array(
            'page' => $page,
            'per_page' => $per_page,
        );
        
        if ($status) {
            $args['status'] = $status;
        }
        
        $reports = AAP_Database::get_reports($args);
        $total = AAP_Database::count_reports($status);
        
        $data = array();
        foreach ($reports as $report) {
            $report_data = maybe_unserialize($report['report_data']);
            
            $data[] = array(
                'id' => $report['id'],
                'website_url' => $report['website_url'],
                'customer_email' => $report['customer_email'],
                'status' => $report['status'],
                'package_type' => $report['package_type'],
                'score' => $report_data['summary']['score'] ?? null,
                'created_at' => $report['created_at'],
                'completed_at' => $report['completed_at'],
            );
        }
        
        $response = rest_ensure_response($data);
        
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));
        
        return $response;
    }
    
    /**
     * Create report (admin only)
     */
    public static function create_report($request) {
        $url = $request->get_param('url');
        $package = $request->get_param('package');
        
        $result = AAP_PayPal::create_admin_order($package, $url);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'report_id' => $result['report_id'],
            'message' => 'Scan started successfully',
        ));
    }
    
    /**
     * Delete report (admin only)
     */
    public static function delete_report($request) {
        $report_id = $request->get_param('id');
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return new WP_Error('not_found', 'Report not found', array('status' => 404));
        }
        
        // Delete associated files
        if (!empty($report['pdf_path']) && file_exists($report['pdf_path'])) {
            unlink($report['pdf_path']);
        }
        
        $pages = AAP_Database::get_scanned_pages($report_id);
        foreach ($pages as $page) {
            if (!empty($page['screenshot_path']) && file_exists($page['screenshot_path'])) {
                unlink($page['screenshot_path']);
            }
        }
        
        AAP_Database::delete_report($report_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Report deleted successfully',
        ));
    }
    
    /**
     * Rescan report (admin only)
     */
    public static function rescan_report($request) {
        $report_id = $request->get_param('id');
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return new WP_Error('not_found', 'Report not found', array('status' => 404));
        }
        
        AAP_Database::update_report($report_id, array(
            'status' => 'pending',
            'progress' => 0,
        ));
        
        AAP_Database::delete_scanned_pages($report_id);
        
        wp_schedule_single_event(time() + 5, 'aap_run_accessibility_scan', array($report_id));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Rescan scheduled',
        ));
    }
    
    /**
     * Get statistics (admin only)
     */
    public static function get_statistics($request) {
        $period = $request->get_param('period');
        $since = date('Y-m-d H:i:s', strtotime("-{$period} days"));
        
        return rest_ensure_response(array(
            'period' => $period,
            'total_reports' => AAP_Database::count_reports_since($since),
            'completed_reports' => AAP_Database::count_reports_since($since, 'completed'),
            'failed_reports' => AAP_Database::count_reports_since($since, 'failed'),
            'total_revenue' => AAP_Database::sum_transactions_since($since),
            'average_score' => AAP_Database::average_score_since($since),
            'popular_packages' => AAP_Database::get_popular_packages($since),
        ));
    }
    
    /**
     * List transactions (admin only)
     */
    public static function list_transactions($request) {
        $page = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100);
        
        $transactions = AAP_Database::get_transactions(array(
            'page' => $page,
            'per_page' => $per_page,
        ));
        
        $total = AAP_Database::count_transactions();
        
        $response = rest_ensure_response($transactions);
        
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));
        
        return $response;
    }
    
    /**
     * Handle PayPal webhook
     */
    public static function handle_paypal_webhook($request) {
        $body = $request->get_body();
        $headers = $request->get_headers();
        
        // Verify webhook signature (in production)
        // For now, just log and process
        
        $event = json_decode($body, true);
        
        if (!$event || !isset($event['event_type'])) {
            return new WP_Error('invalid_payload', 'Invalid webhook payload', array('status' => 400));
        }
        
        $event_type = $event['event_type'];
        $resource = $event['resource'] ?? array();
        
        switch ($event_type) {
            case 'CHECKOUT.ORDER.APPROVED':
                // Order approved, ready for capture
                break;
                
            case 'PAYMENT.CAPTURE.COMPLETED':
                $order_id = $resource['supplementary_data']['related_ids']['order_id'] ?? '';
                if ($order_id) {
                    AAP_PayPal::capture_payment($order_id);
                }
                break;
                
            case 'PAYMENT.CAPTURE.REFUNDED':
                // Handle refund
                $transaction_id = $resource['id'] ?? '';
                if ($transaction_id) {
                    $transaction = AAP_Database::get_transaction_by_paypal_id($transaction_id);
                    if ($transaction) {
                        AAP_Database::update_transaction($transaction['id'], array(
                            'status' => 'refunded',
                        ));
                    }
                }
                break;
        }
        
        return rest_ensure_response(array('received' => true));
    }
}

// Initialize API
AAP_API::init();
