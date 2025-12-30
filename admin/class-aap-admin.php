<?php
/**
 * Admin Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hooks are registered in main plugin file
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'accessibility-audit') === false && strpos($hook, 'aap-') === false) {
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
            array('jquery', 'wp-api'),
            AAP_VERSION,
            true
        );
        
        wp_localize_script('aap-admin', 'aapAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('accessibility-audit/v1/'),
            'nonce' => wp_create_nonce('aap_admin_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
        ));
        
        // Chart.js for dashboard
        if ($hook === 'toplevel_page_accessibility-audit-pro') {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
                array(),
                '4.0',
                true
            );
        }
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('aap_settings', 'aap_settings', array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
        ));
    }
    
    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = array();
        
        // Company settings
        $sanitized['company_name'] = sanitize_text_field($input['company_name'] ?? '');
        $sanitized['company_email'] = sanitize_email($input['company_email'] ?? '');
        $sanitized['company_logo'] = esc_url_raw($input['company_logo'] ?? '');
        
        // PayPal settings
        $sanitized['paypal_sandbox'] = !empty($input['paypal_sandbox']);
        $sanitized['paypal_sandbox_client_id'] = sanitize_text_field($input['paypal_sandbox_client_id'] ?? '');
        $sanitized['paypal_sandbox_secret'] = sanitize_text_field($input['paypal_sandbox_secret'] ?? '');
        $sanitized['paypal_live_client_id'] = sanitize_text_field($input['paypal_live_client_id'] ?? '');
        $sanitized['paypal_live_secret'] = sanitize_text_field($input['paypal_live_secret'] ?? '');
        
        // Currency settings
        $sanitized['currency'] = sanitize_text_field($input['currency'] ?? 'USD');
        $sanitized['currency_symbol'] = sanitize_text_field($input['currency_symbol'] ?? '$');
        
        // Report settings
        $sanitized['report_header_color'] = sanitize_hex_color($input['report_header_color'] ?? '#07599c');
        $sanitized['report_accent_color'] = sanitize_hex_color($input['report_accent_color'] ?? '#09e1c0');
        $sanitized['show_powered_by'] = !empty($input['show_powered_by']);
        
        // Feature settings
        $sanitized['free_for_admin'] = !empty($input['free_for_admin']);
        $sanitized['max_free_previews'] = absint($input['max_free_previews'] ?? 3);
        $sanitized['enable_api'] = !empty($input['enable_api']);
        
        // Screenshot settings
        $sanitized['screenshot_provider'] = sanitize_text_field($input['screenshot_provider'] ?? 'internal');
        $sanitized['screenshot_api_key'] = sanitize_text_field($input['screenshot_api_key'] ?? '');
        
        // Email settings
        $sanitized['send_admin_digest'] = !empty($input['send_admin_digest']);
        
        return $sanitized;
    }
    
    /**
     * Render dashboard
     */
    public static function render_dashboard() {
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $stats = array(
            'total_reports' => AAP_Database::count_reports_since($since),
            'completed_reports' => AAP_Database::count_reports_since($since, 'completed'),
            'failed_reports' => AAP_Database::count_reports_since($since, 'failed'),
            'pending_reports' => AAP_Database::count_reports_since($since, 'pending'),
            'total_revenue' => AAP_Database::sum_transactions_since($since),
            'average_score' => AAP_Database::average_score_since($since),
        );
        
        $recent_reports = AAP_Database::get_recent_reports(10);
        $popular_packages = AAP_Database::get_popular_packages($since);
        
        ?>
        <div class="wrap aap-admin-wrap">
            <h1 class="aap-admin-title">
                <span class="dashicons dashicons-universal-access-alt"></span>
                Accessibility Audit Pro - Dashboard
            </h1>
            
            <!-- Stats Cards -->
            <div class="aap-stats-cards">
                <div class="aap-stat-card">
                    <div class="aap-stat-icon aap-stat-icon-primary">
                        <span class="dashicons dashicons-analytics"></span>
                    </div>
                    <div class="aap-stat-content">
                        <span class="aap-stat-value"><?php echo esc_html($stats['total_reports']); ?></span>
                        <span class="aap-stat-label">Total Scans (30 days)</span>
                    </div>
                </div>
                
                <div class="aap-stat-card">
                    <div class="aap-stat-icon aap-stat-icon-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="aap-stat-content">
                        <span class="aap-stat-value"><?php echo esc_html($stats['completed_reports']); ?></span>
                        <span class="aap-stat-label">Completed</span>
                    </div>
                </div>
                
                <div class="aap-stat-card">
                    <div class="aap-stat-icon aap-stat-icon-warning">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="aap-stat-content">
                        <span class="aap-stat-value"><?php echo esc_html($stats['pending_reports']); ?></span>
                        <span class="aap-stat-label">Pending</span>
                    </div>
                </div>
                
                <div class="aap-stat-card">
                    <div class="aap-stat-icon aap-stat-icon-info">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="aap-stat-content">
                        <span class="aap-stat-value"><?php echo $stats['average_score'] ? number_format($stats['average_score'], 1) : 'N/A'; ?></span>
                        <span class="aap-stat-label">Avg Score</span>
                    </div>
                </div>
                
                <div class="aap-stat-card">
                    <div class="aap-stat-icon aap-stat-icon-success">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="aap-stat-content">
                        <span class="aap-stat-value">$<?php echo number_format($stats['total_revenue'], 2); ?></span>
                        <span class="aap-stat-label">Revenue (30 days)</span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="aap-quick-actions">
                <h2>Quick Actions</h2>
                <div class="aap-action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=aap-new-scan'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        New Scan
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=aap-reports'); ?>" class="button">
                        <span class="dashicons dashicons-media-document"></span>
                        View Reports
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        Settings
                    </a>
                </div>
            </div>
            
            <!-- Recent Reports -->
            <div class="aap-recent-reports">
                <h2>Recent Reports</h2>
                <?php if (empty($recent_reports)) : ?>
                    <p>No reports yet. <a href="<?php echo admin_url('admin.php?page=aap-new-scan'); ?>">Start your first scan</a></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Website</th>
                                <th>Customer</th>
                                <th>Package</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reports as $report) : 
                                $report_data = maybe_unserialize($report['report_data']);
                                $score = $report_data['summary']['score'] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($report['website_url']); ?>" target="_blank">
                                        <?php echo esc_html(parse_url($report['website_url'], PHP_URL_HOST)); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($report['customer_email']); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $report['package_type']))); ?></td>
                                <td>
                                    <?php if ($score !== null) : ?>
                                        <span class="aap-score-badge <?php echo self::get_score_class($score); ?>">
                                            <?php echo number_format($score, 1); ?>
                                        </span>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="aap-status-badge aap-status-<?php echo esc_attr($report['status']); ?>">
                                        <?php echo esc_html(ucfirst($report['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($report['created_at']))); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=aap-reports&action=view&id=' . $report['id']); ?>" class="button button-small">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Render reports page
     */
    public static function render_reports() {
        $action = sanitize_text_field($_GET['action'] ?? 'list');
        $report_id = intval($_GET['id'] ?? 0);
        
        if ($action === 'view' && $report_id) {
            self::render_single_report($report_id);
            return;
        }
        
        // List reports
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $status = sanitize_text_field($_GET['status'] ?? '');
        
        $args = array(
            'page' => $page,
            'per_page' => $per_page,
        );
        
        if ($status) {
            $args['status'] = $status;
        }
        
        $reports = AAP_Database::get_reports($args);
        $total = AAP_Database::count_reports($status);
        $total_pages = ceil($total / $per_page);
        
        ?>
        <div class="wrap aap-admin-wrap">
            <h1 class="aap-admin-title">
                <span class="dashicons dashicons-media-document"></span>
                Reports
            </h1>
            
            <!-- Filters -->
            <div class="aap-filters">
                <form method="get">
                    <input type="hidden" name="page" value="aap-reports">
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        <option value="scanning" <?php selected($status, 'scanning'); ?>>Scanning</option>
                        <option value="failed" <?php selected($status, 'failed'); ?>>Failed</option>
                    </select>
                    <button type="submit" class="button">Filter</button>
                </form>
            </div>
            
            <!-- Reports Table -->
            <?php if (empty($reports)) : ?>
                <p>No reports found.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Website</th>
                            <th>Customer</th>
                            <th>Package</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report) : 
                            $report_data = maybe_unserialize($report['report_data']);
                            $score = $report_data['summary']['score'] ?? null;
                        ?>
                        <tr>
                            <td><?php echo esc_html($report['id']); ?></td>
                            <td>
                                <a href="<?php echo esc_url($report['website_url']); ?>" target="_blank">
                                    <?php echo esc_html(parse_url($report['website_url'], PHP_URL_HOST)); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($report['customer_email']); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $report['package_type']))); ?></td>
                            <td>
                                <?php if ($score !== null) : ?>
                                    <span class="aap-score-badge <?php echo self::get_score_class($score); ?>">
                                        <?php echo number_format($score, 1); ?>
                                    </span>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="aap-status-badge aap-status-<?php echo esc_attr($report['status']); ?>">
                                    <?php echo esc_html(ucfirst($report['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('M j, Y g:i a', strtotime($report['created_at']))); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=aap-reports&action=view&id=' . $report['id']); ?>" class="button button-small">View</a>
                                <?php if ($report['status'] === 'completed') : ?>
                                    <a href="<?php echo esc_url(AAP_PDF_Generator::get_download_url($report['id'])); ?>" class="button button-small">PDF</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $page,
                                'total' => $total_pages,
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Render single report view
     */
    private static function render_single_report($report_id) {
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            echo '<div class="wrap"><p>Report not found.</p></div>';
            return;
        }
        
        $report_data = maybe_unserialize($report['report_data']);
        $pages = AAP_Database::get_scanned_pages($report_id);
        
        ?>
        <div class="wrap aap-admin-wrap">
            <h1 class="aap-admin-title">
                <a href="<?php echo admin_url('admin.php?page=aap-reports'); ?>" class="page-title-action">&larr; Back to Reports</a>
                Report #<?php echo esc_html($report_id); ?>
            </h1>
            
            <!-- Report Header -->
            <div class="aap-report-header">
                <div class="aap-report-info">
                    <h2><?php echo esc_html($report['website_url']); ?></h2>
                    <p>
                        <strong>Customer:</strong> <?php echo esc_html($report['customer_email']); ?><br>
                        <strong>Package:</strong> <?php echo esc_html(ucfirst(str_replace('_', ' ', $report['package_type']))); ?><br>
                        <strong>Created:</strong> <?php echo esc_html(date('F j, Y g:i a', strtotime($report['created_at']))); ?><br>
                        <strong>Status:</strong> 
                        <span class="aap-status-badge aap-status-<?php echo esc_attr($report['status']); ?>">
                            <?php echo esc_html(ucfirst($report['status'])); ?>
                        </span>
                    </p>
                </div>
                
                <div class="aap-report-actions">
                    <?php if ($report['status'] === 'completed') : ?>
                        <a href="<?php echo esc_url(AAP_PDF_Generator::get_download_url($report_id)); ?>" class="button button-primary">
                            <span class="dashicons dashicons-pdf"></span>
                            Download PDF
                        </a>
                    <?php endif; ?>
                    <button type="button" class="button aap-rescan-btn" data-report-id="<?php echo esc_attr($report_id); ?>">
                        <span class="dashicons dashicons-update"></span>
                        Rescan
                    </button>
                    <button type="button" class="button aap-delete-btn" data-report-id="<?php echo esc_attr($report_id); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        Delete
                    </button>
                </div>
            </div>
            
            <?php if ($report['status'] === 'completed' && $report_data) : ?>
            
            <!-- Score Summary -->
            <div class="aap-score-summary">
                <div class="aap-main-score">
                    <div class="aap-score-circle <?php echo self::get_score_class($report_data['summary']['score'] ?? 0); ?>">
                        <span class="aap-score-value"><?php echo number_format($report_data['summary']['score'] ?? 0, 1); ?></span>
                        <span class="aap-score-label">/ 10</span>
                    </div>
                    <span class="aap-score-title">Accessibility Score</span>
                </div>
                
                <div class="aap-score-stats">
                    <div class="aap-score-stat">
                        <span class="aap-stat-number"><?php echo intval($report_data['summary']['total_pages'] ?? 0); ?></span>
                        <span class="aap-stat-text">Pages</span>
                    </div>
                    <div class="aap-score-stat aap-stat-error">
                        <span class="aap-stat-number"><?php echo intval($report_data['summary']['errors'] ?? 0); ?></span>
                        <span class="aap-stat-text">Errors</span>
                    </div>
                    <div class="aap-score-stat aap-stat-warning">
                        <span class="aap-stat-number"><?php echo intval($report_data['summary']['warnings'] ?? 0); ?></span>
                        <span class="aap-stat-text">Warnings</span>
                    </div>
                    <div class="aap-score-stat aap-stat-success">
                        <span class="aap-stat-number"><?php echo intval($report_data['summary']['passed'] ?? 0); ?></span>
                        <span class="aap-stat-text">Passed</span>
                    </div>
                </div>
            </div>
            
            <!-- Pages Breakdown -->
            <div class="aap-pages-breakdown">
                <h3>Scanned Pages</h3>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>Device</th>
                            <th>Errors</th>
                            <th>Warnings</th>
                            <th>Screenshot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($page['page_url']); ?>" target="_blank">
                                    <?php echo esc_html($page['page_title'] ?: $page['page_url']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html(ucfirst($page['device_type'])); ?></td>
                            <td><?php echo intval($page['errors_count']); ?></td>
                            <td><?php echo intval($page['warnings_count']); ?></td>
                            <td>
                                <?php if (!empty($page['screenshot_path']) && file_exists($page['screenshot_path'])) : ?>
                                    <a href="<?php echo esc_url(AAP_Screenshot::get_screenshot_url($page['screenshot_path'])); ?>" target="_blank">
                                        View
                                    </a>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php elseif (in_array($report['status'], array('pending', 'scanning', 'processing'))) : ?>
            
            <div class="aap-progress-section">
                <div class="aap-progress-spinner"></div>
                <h3>Scan in Progress</h3>
                <p>Progress: <?php echo intval($report['progress'] ?? 0); ?>%</p>
                <div class="aap-progress-bar">
                    <div class="aap-progress-fill" style="width: <?php echo intval($report['progress'] ?? 0); ?>%"></div>
                </div>
                <p><em>This page will refresh automatically...</em></p>
            </div>
            
            <script>setTimeout(function(){ location.reload(); }, 10000);</script>
            
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Render new scan page
     */
    public static function render_new_scan() {
        $packages = AAP_Settings::get_packages();
        
        ?>
        <div class="wrap aap-admin-wrap">
            <h1 class="aap-admin-title">
                <span class="dashicons dashicons-search"></span>
                New Accessibility Scan
            </h1>
            
            <div class="aap-admin-notice">
                <p><strong>Admin Access:</strong> As an administrator, you can run unlimited free scans for client prospecting!</p>
            </div>
            
            <form id="aap-admin-scan-form" class="aap-admin-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="website_url">Website URL</label>
                        </th>
                        <td>
                            <input type="url" 
                                   name="website_url" 
                                   id="website_url" 
                                   class="regular-text" 
                                   placeholder="https://example.com"
                                   required>
                            <p class="description">Enter the full URL of the website to scan.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="package_type">Package</label>
                        </th>
                        <td>
                            <select name="package_type" id="package_type">
                                <?php foreach ($packages as $key => $package) : ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($package['name']); ?> (<?php echo $package['pages']; ?> pages)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-search"></span>
                        Start Scan
                    </button>
                </p>
            </form>
            
            <div id="aap-scan-result" class="aap-scan-result" style="display: none;"></div>
        </div>
        <?php
    }
    
    /**
     * Render transactions page
     */
    public static function render_transactions() {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        
        $transactions = AAP_Database::get_transactions(array(
            'page' => $page,
            'per_page' => $per_page,
        ));
        
        $total = AAP_Database::count_transactions();
        $total_pages = ceil($total / $per_page);
        
        ?>
        <div class="wrap aap-admin-wrap">
            <h1 class="aap-admin-title">
                <span class="dashicons dashicons-money-alt"></span>
                Transactions
            </h1>
            
            <?php if (empty($transactions)) : ?>
                <p>No transactions yet.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction) : ?>
                        <tr>
                            <td><?php echo esc_html($transaction['id']); ?></td>
                            <td><?php echo esc_html($transaction['reference_id']); ?></td>
                            <td><?php echo esc_html($transaction['customer_email']); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $transaction['package_type']))); ?></td>
                            <td>
                                <?php echo esc_html($transaction['currency']); ?> 
                                <?php echo number_format($transaction['amount'], 2); ?>
                            </td>
                            <td>
                                <span class="aap-status-badge aap-status-<?php echo esc_attr($transaction['status']); ?>">
                                    <?php echo esc_html(ucfirst($transaction['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('M j, Y g:i a', strtotime($transaction['created_at']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $page,
                                'total' => $total_pages,
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings() {
        $settings = get_option('aap_settings', array());
        
        ?>
        <div class="wrap aap-admin-wrap">
            <h1 class="aap-admin-title">
                <span class="dashicons dashicons-admin-settings"></span>
                Settings
            </h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('aap_settings'); ?>
                
                <h2 class="title">Company Information</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="company_name">Company Name</label></th>
                        <td>
                            <input type="text" 
                                   name="aap_settings[company_name]" 
                                   id="company_name" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['company_name'] ?? get_bloginfo('name')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="company_email">Company Email</label></th>
                        <td>
                            <input type="email" 
                                   name="aap_settings[company_email]" 
                                   id="company_email" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['company_email'] ?? get_option('admin_email')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="company_logo">Company Logo URL</label></th>
                        <td>
                            <input type="url" 
                                   name="aap_settings[company_logo]" 
                                   id="company_logo" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['company_logo'] ?? ''); ?>">
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">PayPal Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="aap_settings[paypal_sandbox]" 
                                       value="1" 
                                       <?php checked(!empty($settings['paypal_sandbox']), true); ?>>
                                Enable Sandbox Mode
                            </label>
                            <p class="description">Use PayPal sandbox for testing. Uncheck for live payments.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paypal_sandbox_client_id">Sandbox Client ID</label></th>
                        <td>
                            <input type="text" 
                                   name="aap_settings[paypal_sandbox_client_id]" 
                                   id="paypal_sandbox_client_id" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['paypal_sandbox_client_id'] ?? ''); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paypal_sandbox_secret">Sandbox Secret</label></th>
                        <td>
                            <input type="password" 
                                   name="aap_settings[paypal_sandbox_secret]" 
                                   id="paypal_sandbox_secret" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['paypal_sandbox_secret'] ?? ''); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paypal_live_client_id">Live Client ID</label></th>
                        <td>
                            <input type="text" 
                                   name="aap_settings[paypal_live_client_id]" 
                                   id="paypal_live_client_id" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['paypal_live_client_id'] ?? ''); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paypal_live_secret">Live Secret</label></th>
                        <td>
                            <input type="password" 
                                   name="aap_settings[paypal_live_secret]" 
                                   id="paypal_live_secret" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['paypal_live_secret'] ?? ''); ?>">
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">Currency</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="currency">Currency Code</label></th>
                        <td>
                            <select name="aap_settings[currency]" id="currency">
                                <option value="USD" <?php selected($settings['currency'] ?? 'USD', 'USD'); ?>>USD</option>
                                <option value="EUR" <?php selected($settings['currency'] ?? 'USD', 'EUR'); ?>>EUR</option>
                                <option value="GBP" <?php selected($settings['currency'] ?? 'USD', 'GBP'); ?>>GBP</option>
                                <option value="CAD" <?php selected($settings['currency'] ?? 'USD', 'CAD'); ?>>CAD</option>
                                <option value="AUD" <?php selected($settings['currency'] ?? 'USD', 'AUD'); ?>>AUD</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="currency_symbol">Currency Symbol</label></th>
                        <td>
                            <input type="text" 
                                   name="aap_settings[currency_symbol]" 
                                   id="currency_symbol" 
                                   class="small-text" 
                                   value="<?php echo esc_attr($settings['currency_symbol'] ?? '$'); ?>">
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">Report Branding</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="report_header_color">Header Color</label></th>
                        <td>
                            <input type="color" 
                                   name="aap_settings[report_header_color]" 
                                   id="report_header_color" 
                                   value="<?php echo esc_attr($settings['report_header_color'] ?? '#07599c'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="report_accent_color">Accent Color</label></th>
                        <td>
                            <input type="color" 
                                   name="aap_settings[report_accent_color]" 
                                   id="report_accent_color" 
                                   value="<?php echo esc_attr($settings['report_accent_color'] ?? '#09e1c0'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Powered By</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="aap_settings[show_powered_by]" 
                                       value="1" 
                                       <?php checked(!empty($settings['show_powered_by']), true); ?>>
                                Show "Powered by Accessibility Audit Pro" in reports
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">Features</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Admin Free Access</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="aap_settings[free_for_admin]" 
                                       value="1" 
                                       <?php checked($settings['free_for_admin'] ?? true, true); ?>>
                                Allow site administrators to run free scans
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_free_previews">Max Free Previews</label></th>
                        <td>
                            <input type="number" 
                                   name="aap_settings[max_free_previews]" 
                                   id="max_free_previews" 
                                   class="small-text" 
                                   value="<?php echo esc_attr($settings['max_free_previews'] ?? 3); ?>"
                                   min="0"
                                   max="10">
                            <p class="description">Maximum free preview scans per IP per day.</p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">Screenshot Provider</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="screenshot_provider">Provider</label></th>
                        <td>
                            <select name="aap_settings[screenshot_provider]" id="screenshot_provider">
                                <option value="internal" <?php selected($settings['screenshot_provider'] ?? 'internal', 'internal'); ?>>Internal (Google PageSpeed)</option>
                                <option value="screenshotmachine" <?php selected($settings['screenshot_provider'] ?? '', 'screenshotmachine'); ?>>ScreenshotMachine</option>
                                <option value="screenshotlayer" <?php selected($settings['screenshot_provider'] ?? '', 'screenshotlayer'); ?>>Screenshotlayer</option>
                                <option value="apiflash" <?php selected($settings['screenshot_provider'] ?? '', 'apiflash'); ?>>ApiFlash</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="screenshot_api_key">API Key</label></th>
                        <td>
                            <input type="text" 
                                   name="aap_settings[screenshot_api_key]" 
                                   id="screenshot_api_key" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($settings['screenshot_api_key'] ?? ''); ?>">
                            <p class="description">Required for external screenshot providers.</p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">Email Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Daily Digest</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="aap_settings[send_admin_digest]" 
                                       value="1" 
                                       <?php checked(!empty($settings['send_admin_digest']), true); ?>>
                                Send daily statistics email to admin
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Email</th>
                        <td>
                            <button type="button" class="button" id="aap-send-test-email">
                                Send Test Email
                            </button>
                            <span id="aap-test-email-result"></span>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get score CSS class
     */
    private static function get_score_class($score) {
        if ($score >= 8) {
            return 'aap-score-good';
        } elseif ($score >= 5) {
            return 'aap-score-medium';
        }
        return 'aap-score-poor';
    }
}

// Initialize admin
AAP_Admin::init();
