<?php
/**
 * PayPal Payment Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_PayPal {
    
    /**
     * PayPal API endpoints
     */
    private static $sandbox_api = 'https://api-m.sandbox.paypal.com';
    private static $live_api = 'https://api-m.paypal.com';
    
    /**
     * Get PayPal API base URL
     */
    public static function get_api_url() {
        $sandbox = AAP_Settings::get_option('paypal_sandbox', true);
        return $sandbox ? self::$sandbox_api : self::$live_api;
    }
    
    /**
     * Get PayPal client ID
     */
    public static function get_client_id() {
        $sandbox = AAP_Settings::get_option('paypal_sandbox', true);
        return $sandbox 
            ? AAP_Settings::get_option('paypal_sandbox_client_id')
            : AAP_Settings::get_option('paypal_live_client_id');
    }
    
    /**
     * Get PayPal client secret
     */
    public static function get_client_secret() {
        $sandbox = AAP_Settings::get_option('paypal_sandbox', true);
        return $sandbox 
            ? AAP_Settings::get_option('paypal_sandbox_secret')
            : AAP_Settings::get_option('paypal_live_secret');
    }
    
    /**
     * Get OAuth 2.0 access token
     */
    public static function get_access_token() {
        $client_id = self::get_client_id();
        $client_secret = self::get_client_secret();
        
        if (empty($client_id) || empty($client_secret)) {
            return new WP_Error('paypal_config', 'PayPal credentials not configured');
        }
        
        // Check for cached token
        $cached_token = get_transient('aap_paypal_access_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        $response = wp_remote_post(self::get_api_url() . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['access_token'])) {
            return new WP_Error('paypal_token', $body['error_description'] ?? 'Failed to get access token');
        }
        
        // Cache the token for its validity period minus 60 seconds
        $expires_in = ($body['expires_in'] ?? 3600) - 60;
        set_transient('aap_paypal_access_token', $body['access_token'], $expires_in);
        
        return $body['access_token'];
    }
    
    /**
     * Create PayPal order
     */
    public static function create_order($package_type, $return_url, $cancel_url, $customer_data = array()) {
        $access_token = self::get_access_token();
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $packages = AAP_Settings::get_packages();
        
        if (!isset($packages[$package_type])) {
            return new WP_Error('invalid_package', 'Invalid package type');
        }
        
        $package = $packages[$package_type];
        $price = floatval($package['price']);
        
        // Apply discount if provided
        if (!empty($customer_data['discount_code'])) {
            $discount = self::validate_discount_code($customer_data['discount_code']);
            if (!is_wp_error($discount)) {
                if ($discount['type'] === 'percentage') {
                    $price = $price * (1 - ($discount['amount'] / 100));
                } else {
                    $price = max(0, $price - $discount['amount']);
                }
            }
        }
        
        // Create reference ID for tracking
        $reference_id = 'AAP-' . time() . '-' . wp_rand(1000, 9999);
        
        $order_data = array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'reference_id' => $reference_id,
                    'description' => 'Accessibility Audit - ' . $package['name'],
                    'amount' => array(
                        'currency_code' => AAP_Settings::get_option('currency', 'USD'),
                        'value' => number_format($price, 2, '.', ''),
                        'breakdown' => array(
                            'item_total' => array(
                                'currency_code' => AAP_Settings::get_option('currency', 'USD'),
                                'value' => number_format($price, 2, '.', ''),
                            ),
                        ),
                    ),
                    'items' => array(
                        array(
                            'name' => 'Accessibility Audit - ' . $package['name'],
                            'description' => $package['pages'] . ' page audit with screenshots',
                            'quantity' => '1',
                            'unit_amount' => array(
                                'currency_code' => AAP_Settings::get_option('currency', 'USD'),
                                'value' => number_format($price, 2, '.', ''),
                            ),
                            'category' => 'DIGITAL_GOODS',
                        ),
                    ),
                ),
            ),
            'application_context' => array(
                'brand_name' => AAP_Settings::get_option('company_name', get_bloginfo('name')),
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => $return_url,
                'cancel_url' => $cancel_url,
            ),
        );
        
        $response = wp_remote_post(self::get_api_url() . '/v2/checkout/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => $reference_id,
            ),
            'body' => wp_json_encode($order_data),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['id'])) {
            $error_msg = 'Failed to create PayPal order';
            if (isset($body['details'][0]['description'])) {
                $error_msg = $body['details'][0]['description'];
            }
            return new WP_Error('paypal_order', $error_msg);
        }
        
        // Store pending transaction
        AAP_Database::create_transaction(array(
            'order_id' => $body['id'],
            'reference_id' => $reference_id,
            'package_type' => $package_type,
            'amount' => $price,
            'currency' => AAP_Settings::get_option('currency', 'USD'),
            'status' => 'pending',
            'customer_email' => $customer_data['email'] ?? '',
            'customer_name' => $customer_data['name'] ?? '',
            'website_url' => $customer_data['website_url'] ?? '',
            'discount_code' => $customer_data['discount_code'] ?? '',
            'paypal_data' => wp_json_encode($body),
        ));
        
        // Get approval URL
        $approval_url = '';
        foreach ($body['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approval_url = $link['href'];
                break;
            }
        }
        
        return array(
            'order_id' => $body['id'],
            'reference_id' => $reference_id,
            'approval_url' => $approval_url,
            'status' => $body['status'],
        );
    }
    
    /**
     * Capture PayPal payment
     */
    public static function capture_payment($order_id) {
        $access_token = self::get_access_token();
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $response = wp_remote_post(self::get_api_url() . '/v2/checkout/orders/' . $order_id . '/capture', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => '{}',
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['status']) || $body['status'] !== 'COMPLETED') {
            $error_msg = 'Payment capture failed';
            if (isset($body['details'][0]['description'])) {
                $error_msg = $body['details'][0]['description'];
            }
            return new WP_Error('paypal_capture', $error_msg);
        }
        
        // Update transaction
        $transaction = AAP_Database::get_transaction_by_order_id($order_id);
        
        if ($transaction) {
            $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? array();
            
            AAP_Database::update_transaction($transaction['id'], array(
                'status' => 'completed',
                'transaction_id' => $capture['id'] ?? '',
                'paypal_data' => wp_json_encode($body),
                'completed_at' => current_time('mysql'),
            ));
            
            // Trigger the audit
            self::trigger_audit_after_payment($transaction);
        }
        
        return array(
            'status' => 'completed',
            'order_id' => $order_id,
            'capture_id' => $capture['id'] ?? '',
            'payer' => $body['payer'] ?? array(),
        );
    }
    
    /**
     * Get order details
     */
    public static function get_order($order_id) {
        $access_token = self::get_access_token();
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $response = wp_remote_get(self::get_api_url() . '/v2/checkout/orders/' . $order_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Validate discount code
     */
    public static function validate_discount_code($code) {
        $discount_codes = AAP_Settings::get_option('discount_codes', array());
        
        if (empty($discount_codes)) {
            return new WP_Error('invalid_code', 'Invalid discount code');
        }
        
        $code = strtoupper(trim($code));
        
        foreach ($discount_codes as $discount) {
            if (strtoupper($discount['code']) === $code) {
                // Check if active
                if (!$discount['active']) {
                    return new WP_Error('inactive_code', 'This discount code is no longer active');
                }
                
                // Check expiry
                if (!empty($discount['expires']) && strtotime($discount['expires']) < time()) {
                    return new WP_Error('expired_code', 'This discount code has expired');
                }
                
                // Check usage limit
                if (!empty($discount['usage_limit'])) {
                    $usage_count = AAP_Database::count_discount_usage($code);
                    if ($usage_count >= $discount['usage_limit']) {
                        return new WP_Error('usage_exceeded', 'This discount code has reached its usage limit');
                    }
                }
                
                return array(
                    'code' => $code,
                    'type' => $discount['type'], // 'percentage' or 'fixed'
                    'amount' => floatval($discount['amount']),
                    'description' => $discount['description'] ?? '',
                );
            }
        }
        
        return new WP_Error('invalid_code', 'Invalid discount code');
    }
    
    /**
     * Trigger audit after successful payment
     */
    private static function trigger_audit_after_payment($transaction) {
        $packages = AAP_Settings::get_packages();
        $package = $packages[$transaction['package_type']] ?? null;
        
        if (!$package) {
            return;
        }
        
        // Create report record
        $report_id = AAP_Database::create_report(array(
            'website_url' => $transaction['website_url'],
            'customer_email' => $transaction['customer_email'],
            'customer_name' => $transaction['customer_name'],
            'package_type' => $transaction['package_type'],
            'transaction_id' => $transaction['id'],
            'status' => 'pending',
        ));
        
        // Schedule the scan
        wp_schedule_single_event(time() + 10, 'aap_run_accessibility_scan', array($report_id));
        
        // Send confirmation email
        AAP_Email::send_order_confirmation($transaction, $report_id);
    }
    
    /**
     * Process PayPal IPN (Instant Payment Notification)
     */
    public static function handle_ipn() {
        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }
        
        // Verify with PayPal
        $req = 'cmd=_notify-validate';
        foreach ($myPost as $key => $value) {
            $value = urlencode($value);
            $req .= "&$key=$value";
        }
        
        $sandbox = AAP_Settings::get_option('paypal_sandbox', true);
        $paypal_url = $sandbox 
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';
        
        $response = wp_remote_post($paypal_url, array(
            'body' => $req,
            'timeout' => 30,
            'httpversion' => '1.1',
        ));
        
        if (is_wp_error($response)) {
            error_log('PayPal IPN Error: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (strcmp($body, 'VERIFIED') === 0) {
            // Process the verified IPN
            $payment_status = sanitize_text_field($_POST['payment_status'] ?? '');
            $txn_id = sanitize_text_field($_POST['txn_id'] ?? '');
            $custom = sanitize_text_field($_POST['custom'] ?? '');
            
            if ($payment_status === 'Completed' && !empty($custom)) {
                // Update transaction status
                $transaction = AAP_Database::get_transaction_by_reference_id($custom);
                
                if ($transaction && $transaction['status'] !== 'completed') {
                    AAP_Database::update_transaction($transaction['id'], array(
                        'status' => 'completed',
                        'transaction_id' => $txn_id,
                        'completed_at' => current_time('mysql'),
                    ));
                    
                    self::trigger_audit_after_payment($transaction);
                }
            }
        } else {
            error_log('PayPal IPN: INVALID');
        }
    }
    
    /**
     * Refund payment
     */
    public static function refund_payment($capture_id, $amount = null, $reason = '') {
        $access_token = self::get_access_token();
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $body = array();
        
        if ($amount !== null) {
            $body['amount'] = array(
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => AAP_Settings::get_option('currency', 'USD'),
            );
        }
        
        if (!empty($reason)) {
            $body['note_to_payer'] = substr($reason, 0, 255);
        }
        
        $response = wp_remote_post(self::get_api_url() . '/v2/payments/captures/' . $capture_id . '/refund', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($result['id'])) {
            $error_msg = 'Refund failed';
            if (isset($result['details'][0]['description'])) {
                $error_msg = $result['details'][0]['description'];
            }
            return new WP_Error('paypal_refund', $error_msg);
        }
        
        return array(
            'refund_id' => $result['id'],
            'status' => $result['status'],
            'amount' => $result['amount'] ?? array(),
        );
    }
    
    /**
     * Check if current user is admin (free access)
     */
    public static function is_admin_free_access() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $free_for_admin = AAP_Settings::get_option('free_for_admin', true);
        
        if (!$free_for_admin) {
            return false;
        }
        
        return current_user_can('manage_options');
    }
    
    /**
     * Create free admin order
     */
    public static function create_admin_order($package_type, $website_url) {
        if (!self::is_admin_free_access()) {
            return new WP_Error('not_admin', 'Admin access required for free audits');
        }
        
        $packages = AAP_Settings::get_packages();
        
        if (!isset($packages[$package_type])) {
            return new WP_Error('invalid_package', 'Invalid package type');
        }
        
        $current_user = wp_get_current_user();
        $reference_id = 'AAP-ADMIN-' . time() . '-' . wp_rand(1000, 9999);
        
        // Create completed transaction
        $transaction_id = AAP_Database::create_transaction(array(
            'order_id' => $reference_id,
            'reference_id' => $reference_id,
            'package_type' => $package_type,
            'amount' => 0,
            'currency' => AAP_Settings::get_option('currency', 'USD'),
            'status' => 'completed',
            'customer_email' => $current_user->user_email,
            'customer_name' => $current_user->display_name,
            'website_url' => $website_url,
            'discount_code' => 'ADMIN_FREE',
            'completed_at' => current_time('mysql'),
        ));
        
        // Get the transaction
        $transaction = AAP_Database::get_transaction($transaction_id);
        
        // Create report and trigger scan
        $report_id = AAP_Database::create_report(array(
            'website_url' => $website_url,
            'customer_email' => $current_user->user_email,
            'customer_name' => $current_user->display_name,
            'package_type' => $package_type,
            'transaction_id' => $transaction_id,
            'status' => 'pending',
        ));
        
        // Schedule the scan
        wp_schedule_single_event(time() + 5, 'aap_run_accessibility_scan', array($report_id));
        
        return array(
            'success' => true,
            'report_id' => $report_id,
            'reference_id' => $reference_id,
        );
    }
    
    /**
     * Get PayPal JavaScript SDK URL
     */
    public static function get_sdk_url() {
        $client_id = self::get_client_id();
        $currency = AAP_Settings::get_option('currency', 'USD');
        
        return add_query_arg(array(
            'client-id' => $client_id,
            'currency' => $currency,
            'intent' => 'capture',
            'enable-funding' => 'card',
        ), 'https://www.paypal.com/sdk/js');
    }
    
    /**
     * Get payment button HTML
     */
    public static function get_payment_buttons_html($container_id = 'paypal-button-container') {
        if (empty(self::get_client_id())) {
            return '<p class="aap-error">PayPal is not configured. Please contact the administrator.</p>';
        }
        
        return '<div id="' . esc_attr($container_id) . '" class="aap-paypal-buttons"></div>';
    }
}
