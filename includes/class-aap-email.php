<?php
/**
 * Email Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Email {
    
    /**
     * Get email headers
     */
    private static function get_headers() {
        $from_name = AAP_Settings::get_option('company_name', get_bloginfo('name'));
        $from_email = AAP_Settings::get_option('company_email', get_option('admin_email'));
        
        return array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );
    }
    
    /**
     * Get email template
     */
    private static function get_template($template_name, $variables = array()) {
        $template_path = AAP_PLUGIN_DIR . 'templates/emails/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return false;
        }
        
        // Extract variables for template
        extract($variables);
        
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
    
    /**
     * Send email wrapper
     */
    public static function send($to, $subject, $content, $attachments = array()) {
        // Apply email wrapper
        $wrapped_content = self::wrap_email_content($subject, $content);
        
        // Log email if debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("AAP Email to: $to, Subject: $subject");
        }
        
        return wp_mail($to, $subject, $wrapped_content, self::get_headers(), $attachments);
    }
    
    /**
     * Wrap content in email template
     */
    private static function wrap_email_content($title, $content) {
        $company_name = AAP_Settings::get_option('company_name', get_bloginfo('name'));
        $header_color = AAP_Settings::get_option('report_header_color', '#07599c');
        $accent_color = AAP_Settings::get_option('report_accent_color', '#09e1c0');
        $logo_url = AAP_Settings::get_option('company_logo_url', '');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?></title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
                .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .email-header { background-color: <?php echo esc_attr($header_color); ?>; padding: 30px; text-align: center; }
                .email-header img { max-width: 150px; height: auto; }
                .email-header h1 { color: #ffffff; margin: 15px 0 0; font-size: 24px; }
                .email-body { padding: 40px 30px; }
                .email-footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 12px 30px; background-color: <?php echo esc_attr($accent_color); ?>; color: #000; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .button:hover { opacity: 0.9; }
                .stats-box { background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .stat-item { display: inline-block; width: 30%; text-align: center; padding: 10px; }
                .stat-value { font-size: 28px; font-weight: bold; color: <?php echo esc_attr($header_color); ?>; }
                .stat-label { font-size: 12px; color: #666; }
                .score-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: bold; }
                .score-good { background-color: #d1fae5; color: #065f46; }
                .score-medium { background-color: #fef3c7; color: #92400e; }
                .score-poor { background-color: #fee2e2; color: #991b1b; }
                ul { padding-left: 20px; }
                li { margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-header">
                    <?php if ($logo_url) : ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?>">
                    <?php else : ?>
                        <h1><?php echo esc_html($company_name); ?></h1>
                    <?php endif; ?>
                </div>
                <div class="email-body">
                    <?php echo $content; ?>
                </div>
                <div class="email-footer">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo esc_html($company_name); ?>. All rights reserved.</p>
                    <p>
                        <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html(home_url()); ?></a>
                    </p>
                    <?php if (AAP_Settings::get_option('show_powered_by', true)) : ?>
                        <p style="margin-top: 15px; font-size: 11px;">Powered by Accessibility Audit Pro</p>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send order confirmation email
     */
    public static function send_order_confirmation($transaction, $report_id) {
        $to = $transaction['customer_email'];
        $subject = 'Order Confirmation - Accessibility Audit';
        
        $packages = AAP_Settings::get_packages();
        $package = $packages[$transaction['package_type']] ?? array();
        
        ob_start();
        ?>
        <h2>Thank You for Your Order!</h2>
        
        <p>Hi <?php echo esc_html($transaction['customer_name'] ?: 'there'); ?>,</p>
        
        <p>We've received your order for an accessibility audit. Your website scan will begin shortly.</p>
        
        <div class="stats-box">
            <p><strong>Order Details:</strong></p>
            <ul style="list-style: none; padding: 0;">
                <li><strong>Reference:</strong> <?php echo esc_html($transaction['reference_id']); ?></li>
                <li><strong>Package:</strong> <?php echo esc_html($package['name'] ?? $transaction['package_type']); ?></li>
                <li><strong>Website:</strong> <?php echo esc_html($transaction['website_url']); ?></li>
                <li><strong>Pages to Scan:</strong> <?php echo esc_html($package['pages'] ?? 'N/A'); ?></li>
                <li><strong>Amount:</strong> <?php echo esc_html(AAP_Settings::get_option('currency_symbol', '$') . number_format($transaction['amount'], 2)); ?></li>
            </ul>
        </div>
        
        <p><strong>What happens next?</strong></p>
        <ol>
            <li>Our system will begin scanning your website automatically.</li>
            <li>We'll capture screenshots across desktop, tablet, and mobile devices.</li>
            <li>A comprehensive WCAG 2.1 compliance report will be generated.</li>
            <li>You'll receive an email with your PDF report within 24 hours.</li>
        </ol>
        
        <p style="text-align: center;">
            <a href="<?php echo esc_url(home_url('/accessibility-audit/?view=status&id=' . $report_id)); ?>" class="button">
                Check Status
            </a>
        </p>
        
        <p>If you have any questions, please don't hesitate to contact us.</p>
        
        <p>Best regards,<br>
        <?php echo esc_html(AAP_Settings::get_option('company_name', get_bloginfo('name'))); ?> Team</p>
        <?php
        $content = ob_get_clean();
        
        return self::send($to, $subject, $content);
    }
    
    /**
     * Send report completion email
     */
    public static function send_report_complete($report_id) {
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return false;
        }
        
        $to = $report['customer_email'];
        $subject = 'Your Accessibility Report is Ready!';
        
        $report_data = maybe_unserialize($report['report_data']);
        $summary = $report_data['summary'] ?? array();
        $score = $summary['score'] ?? 0;
        
        // Determine score class
        if ($score >= 8) {
            $score_class = 'score-good';
            $score_message = 'Great job! Your website has good accessibility.';
        } elseif ($score >= 5) {
            $score_class = 'score-medium';
            $score_message = 'Your website has moderate accessibility and needs some improvements.';
        } else {
            $score_class = 'score-poor';
            $score_message = 'Your website needs significant accessibility improvements.';
        }
        
        $download_url = AAP_PDF_Generator::get_download_url($report_id);
        
        ob_start();
        ?>
        <h2>Your Accessibility Report is Ready!</h2>
        
        <p>Hi <?php echo esc_html($report['customer_name'] ?: 'there'); ?>,</p>
        
        <p>Great news! We've completed the accessibility audit of your website:</p>
        <p><strong><?php echo esc_html($report['website_url']); ?></strong></p>
        
        <div class="stats-box" style="text-align: center;">
            <p style="margin-bottom: 5px; color: #666;">Your Accessibility Score</p>
            <p class="score-badge <?php echo $score_class; ?>" style="font-size: 36px;">
                <?php echo number_format($score, 1); ?>/10
            </p>
            <p style="margin-top: 10px;"><?php echo esc_html($score_message); ?></p>
        </div>
        
        <div class="stats-box">
            <table width="100%" cellpadding="10">
                <tr>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value"><?php echo intval($summary['total_pages'] ?? 0); ?></div>
                        <div class="stat-label">Pages Scanned</div>
                    </td>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value" style="color: #dc2626;"><?php echo intval($summary['errors'] ?? 0); ?></div>
                        <div class="stat-label">Errors</div>
                    </td>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value" style="color: #d97706;"><?php echo intval($summary['warnings'] ?? 0); ?></div>
                        <div class="stat-label">Warnings</div>
                    </td>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value" style="color: #16a34a;"><?php echo intval($summary['passed'] ?? 0); ?></div>
                        <div class="stat-label">Passed</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <p style="text-align: center;">
            <a href="<?php echo esc_url($download_url); ?>" class="button">
                Download PDF Report
            </a>
        </p>
        
        <p><strong>What's in your report:</strong></p>
        <ul>
            <li>Executive Summary with overall assessment</li>
            <li>WCAG 2.1 Level A & AA compliance breakdown</li>
            <li>Detailed issues with specific recommendations</li>
            <li>Screenshots across desktop, tablet, and mobile</li>
            <li>Prioritized action items for remediation</li>
        </ul>
        
        <p><strong>Need help fixing these issues?</strong></p>
        <p>Our team can help you remediate accessibility issues and achieve full WCAG compliance. 
        <a href="<?php echo esc_url(home_url('/contact')); ?>">Contact us</a> for a consultation.</p>
        
        <p>Thank you for choosing our accessibility audit service!</p>
        
        <p>Best regards,<br>
        <?php echo esc_html(AAP_Settings::get_option('company_name', get_bloginfo('name'))); ?> Team</p>
        <?php
        $content = ob_get_clean();
        
        // Attach PDF if it exists
        $attachments = array();
        if (!empty($report['pdf_path']) && file_exists($report['pdf_path'])) {
            $attachments[] = $report['pdf_path'];
        }
        
        return self::send($to, $subject, $content, $attachments);
    }
    
    /**
     * Send report failed notification
     */
    public static function send_report_failed($report_id, $error_message = '') {
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return false;
        }
        
        $to = $report['customer_email'];
        $subject = 'Issue with Your Accessibility Audit';
        
        ob_start();
        ?>
        <h2>We Encountered an Issue</h2>
        
        <p>Hi <?php echo esc_html($report['customer_name'] ?: 'there'); ?>,</p>
        
        <p>We encountered an issue while scanning your website:</p>
        <p><strong><?php echo esc_html($report['website_url']); ?></strong></p>
        
        <?php if ($error_message) : ?>
        <div class="stats-box" style="background-color: #fee2e2;">
            <p><strong>Error Details:</strong></p>
            <p><?php echo esc_html($error_message); ?></p>
        </div>
        <?php endif; ?>
        
        <p>This could happen due to:</p>
        <ul>
            <li>The website is temporarily unavailable</li>
            <li>The website blocks automated scanning</li>
            <li>Network connectivity issues</li>
            <li>The website requires authentication</li>
        </ul>
        
        <p>Our team has been notified and will investigate this issue. We'll either:</p>
        <ol>
            <li>Retry the scan and send your report, or</li>
            <li>Contact you to resolve any issues</li>
        </ol>
        
        <p>If you have any questions, please reply to this email or <a href="<?php echo esc_url(home_url('/contact')); ?>">contact us</a>.</p>
        
        <p>We apologize for any inconvenience.</p>
        
        <p>Best regards,<br>
        <?php echo esc_html(AAP_Settings::get_option('company_name', get_bloginfo('name'))); ?> Team</p>
        <?php
        $content = ob_get_clean();
        
        // Also notify admin
        self::notify_admin_scan_failed($report_id, $error_message);
        
        return self::send($to, $subject, $content);
    }
    
    /**
     * Notify admin of failed scan
     */
    private static function notify_admin_scan_failed($report_id, $error_message) {
        $admin_email = get_option('admin_email');
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return;
        }
        
        $subject = '[AAP Alert] Scan Failed - Report #' . $report_id;
        
        ob_start();
        ?>
        <h2>Accessibility Scan Failed</h2>
        
        <p>A scheduled accessibility scan has failed.</p>
        
        <div class="stats-box">
            <p><strong>Report Details:</strong></p>
            <ul style="list-style: none; padding: 0;">
                <li><strong>Report ID:</strong> <?php echo esc_html($report_id); ?></li>
                <li><strong>Website:</strong> <?php echo esc_html($report['website_url']); ?></li>
                <li><strong>Customer:</strong> <?php echo esc_html($report['customer_email']); ?></li>
                <li><strong>Package:</strong> <?php echo esc_html($report['package_type']); ?></li>
            </ul>
        </div>
        
        <?php if ($error_message) : ?>
        <div class="stats-box" style="background-color: #fee2e2;">
            <p><strong>Error:</strong></p>
            <p><?php echo esc_html($error_message); ?></p>
        </div>
        <?php endif; ?>
        
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aap-reports&action=view&id=' . $report_id)); ?>" class="button">
                View Report Details
            </a>
        </p>
        <?php
        $content = ob_get_clean();
        
        self::send($admin_email, $subject, $content);
    }
    
    /**
     * Send admin daily digest
     */
    public static function send_admin_daily_digest() {
        $admin_email = get_option('admin_email');
        
        // Get stats for last 24 hours
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $total_scans = AAP_Database::count_reports_since($yesterday);
        $completed_scans = AAP_Database::count_reports_since($yesterday, 'completed');
        $failed_scans = AAP_Database::count_reports_since($yesterday, 'failed');
        $revenue = AAP_Database::sum_transactions_since($yesterday);
        
        if ($total_scans === 0 && $revenue === 0) {
            return; // Skip if nothing to report
        }
        
        $subject = '[AAP Digest] Daily Summary - ' . date('M j, Y');
        
        ob_start();
        ?>
        <h2>Daily Accessibility Audit Summary</h2>
        
        <p>Here's your daily summary for <?php echo date('F j, Y'); ?>:</p>
        
        <div class="stats-box">
            <table width="100%" cellpadding="15">
                <tr>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value"><?php echo $total_scans; ?></div>
                        <div class="stat-label">Total Scans</div>
                    </td>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value" style="color: #16a34a;"><?php echo $completed_scans; ?></div>
                        <div class="stat-label">Completed</div>
                    </td>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value" style="color: #dc2626;"><?php echo $failed_scans; ?></div>
                        <div class="stat-label">Failed</div>
                    </td>
                    <td style="text-align: center; width: 25%;">
                        <div class="stat-value" style="color: #16a34a;">
                            <?php echo AAP_Settings::get_option('currency_symbol', '$') . number_format($revenue, 2); ?>
                        </div>
                        <div class="stat-label">Revenue</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <p style="text-align: center;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=accessibility-audit-pro')); ?>" class="button">
                View Dashboard
            </a>
        </p>
        <?php
        $content = ob_get_clean();
        
        self::send($admin_email, $subject, $content);
    }
    
    /**
     * Send test email
     */
    public static function send_test_email($to) {
        $subject = 'Test Email - Accessibility Audit Pro';
        
        ob_start();
        ?>
        <h2>Test Email</h2>
        
        <p>This is a test email from Accessibility Audit Pro.</p>
        
        <p>If you received this email, your email configuration is working correctly!</p>
        
        <div class="stats-box">
            <p><strong>Configuration Details:</strong></p>
            <ul style="list-style: none; padding: 0;">
                <li><strong>From Name:</strong> <?php echo esc_html(AAP_Settings::get_option('company_name', get_bloginfo('name'))); ?></li>
                <li><strong>From Email:</strong> <?php echo esc_html(AAP_Settings::get_option('company_email', get_option('admin_email'))); ?></li>
                <li><strong>Timestamp:</strong> <?php echo current_time('F j, Y g:i a'); ?></li>
            </ul>
        </div>
        
        <p style="text-align: center;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=aap-settings')); ?>" class="button">
                Go to Settings
            </a>
        </p>
        <?php
        $content = ob_get_clean();
        
        return self::send($to, $subject, $content);
    }
}
