<?php
/**
 * Shortcodes for Frontend Display
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Shortcodes {
    
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
     * Initialize shortcodes
     */
    private function init_hooks() {
        add_shortcode('accessibility_audit', array(__CLASS__, 'render_audit_form'));
        add_shortcode('accessibility_audit_pricing', array(__CLASS__, 'render_pricing_table'));
        add_shortcode('accessibility_audit_preview', array(__CLASS__, 'render_preview_scanner'));
        add_shortcode('accessibility_audit_status', array(__CLASS__, 'render_report_status'));
    }
    
    /**
     * Main audit form shortcode
     */
    public static function render_audit_form($atts) {
        $atts = shortcode_atts(array(
            'show_pricing' => 'true',
            'show_preview' => 'true',
            'default_package' => '10_pages',
        ), $atts);
        
        // Check for view parameter
        $view = sanitize_text_field($_GET['view'] ?? '');
        $report_id = intval($_GET['id'] ?? 0);
        
        if ($view === 'status' && $report_id) {
            return self::render_report_status(array('report_id' => $report_id));
        }
        
        // Check for payment status
        $payment_status = sanitize_text_field($_GET['payment'] ?? '');
        
        ob_start();
        ?>
        <div class="aap-audit-container" data-nonce="<?php echo wp_create_nonce('aap_frontend_nonce'); ?>">
            
            <?php if ($payment_status === 'success') : ?>
                <div class="aap-notice aap-notice-success">
                    <h3>Payment Successful!</h3>
                    <p>Thank you for your order. Your accessibility scan will begin shortly. You'll receive an email when your report is ready.</p>
                </div>
            <?php elseif ($payment_status === 'cancelled') : ?>
                <div class="aap-notice aap-notice-warning">
                    <p>Payment was cancelled. You can try again below.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_preview'] === 'true') : ?>
            <!-- Free Preview Section -->
            <div class="aap-preview-section">
                <h3>Try a Free Preview Scan</h3>
                <p>Enter your website URL to see a quick preview of accessibility issues.</p>
                
                <form class="aap-preview-form" id="aap-preview-form">
                    <div class="aap-form-row">
                        <input type="url" 
                               name="preview_url" 
                               id="aap-preview-url" 
                               placeholder="https://yourwebsite.com" 
                               required 
                               class="aap-input aap-input-large">
                        <button type="submit" class="aap-button aap-button-secondary">
                            <span class="aap-button-text">Preview Scan</span>
                            <span class="aap-spinner" style="display: none;"></span>
                        </button>
                    </div>
                </form>
                
                <div id="aap-preview-results" class="aap-preview-results" style="display: none;"></div>
            </div>
            
            <div class="aap-divider">
                <span>Get a Full Report</span>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_pricing'] === 'true') : ?>
            <!-- Pricing Table -->
            <?php echo self::render_pricing_table(array('selectable' => 'true', 'default' => $atts['default_package'])); ?>
            <?php endif; ?>
            
            <!-- Order Form -->
            <div class="aap-order-section">
                <h3>Order Your Accessibility Audit</h3>
                
                <form class="aap-order-form" id="aap-order-form">
                    <div class="aap-form-grid">
                        <div class="aap-form-group">
                            <label for="aap-website-url">Website URL <span class="required">*</span></label>
                            <input type="url" 
                                   name="website_url" 
                                   id="aap-website-url" 
                                   placeholder="https://yourwebsite.com" 
                                   required 
                                   class="aap-input">
                        </div>
                        
                        <div class="aap-form-group">
                            <label for="aap-customer-email">Email Address <span class="required">*</span></label>
                            <input type="email" 
                                   name="customer_email" 
                                   id="aap-customer-email" 
                                   placeholder="you@example.com" 
                                   required 
                                   class="aap-input"
                                   value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
                        </div>
                        
                        <div class="aap-form-group">
                            <label for="aap-customer-name">Your Name</label>
                            <input type="text" 
                                   name="customer_name" 
                                   id="aap-customer-name" 
                                   placeholder="John Doe" 
                                   class="aap-input"
                                   value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>">
                        </div>
                        
                        <div class="aap-form-group">
                            <label for="aap-discount-code">Discount Code</label>
                            <div class="aap-input-with-button">
                                <input type="text" 
                                       name="discount_code" 
                                       id="aap-discount-code" 
                                       placeholder="Enter code" 
                                       class="aap-input">
                                <button type="button" class="aap-button aap-button-small" id="aap-apply-discount">Apply</button>
                            </div>
                            <div id="aap-discount-result" class="aap-discount-result"></div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="package_type" id="aap-package-type" value="<?php echo esc_attr($atts['default_package']); ?>">
                    
                    <!-- Order Summary -->
                    <div class="aap-order-summary">
                        <h4>Order Summary</h4>
                        <div class="aap-summary-row">
                            <span class="aap-summary-label">Package:</span>
                            <span class="aap-summary-value" id="aap-summary-package">-</span>
                        </div>
                        <div class="aap-summary-row">
                            <span class="aap-summary-label">Pages:</span>
                            <span class="aap-summary-value" id="aap-summary-pages">-</span>
                        </div>
                        <div class="aap-summary-row aap-discount-row" style="display: none;">
                            <span class="aap-summary-label">Discount:</span>
                            <span class="aap-summary-value" id="aap-summary-discount">-</span>
                        </div>
                        <div class="aap-summary-row aap-summary-total">
                            <span class="aap-summary-label">Total:</span>
                            <span class="aap-summary-value" id="aap-summary-total">$0.00</span>
                        </div>
                    </div>
                    
                    <?php if (AAP_PayPal::is_admin_free_access()) : ?>
                    <div class="aap-admin-notice">
                        <p><strong>Admin Access:</strong> As a site administrator, you can run audits for free to prospect clients!</p>
                    </div>
                    <button type="submit" class="aap-button aap-button-primary aap-button-large">
                        Start Free Audit
                    </button>
                    <?php else : ?>
                    <!-- PayPal Buttons -->
                    <div id="aap-paypal-container">
                        <?php echo AAP_PayPal::get_payment_buttons_html(); ?>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Features List -->
            <div class="aap-features-list">
                <h4>What's Included in Your Report</h4>
                <div class="aap-features-grid">
                    <div class="aap-feature">
                        <span class="aap-feature-icon">✓</span>
                        <span>Full WCAG 2.1 Level A & AA compliance check</span>
                    </div>
                    <div class="aap-feature">
                        <span class="aap-feature-icon">✓</span>
                        <span>Desktop, tablet, and mobile testing</span>
                    </div>
                    <div class="aap-feature">
                        <span class="aap-feature-icon">✓</span>
                        <span>Screenshots across all device sizes</span>
                    </div>
                    <div class="aap-feature">
                        <span class="aap-feature-icon">✓</span>
                        <span>Detailed issue descriptions</span>
                    </div>
                    <div class="aap-feature">
                        <span class="aap-feature-icon">✓</span>
                        <span>Prioritized recommendations</span>
                    </div>
                    <div class="aap-feature">
                        <span class="aap-feature-icon">✓</span>
                        <span>Professional PDF report</span>
                    </div>
                </div>
            </div>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Pricing table shortcode
     */
    public static function render_pricing_table($atts) {
        $atts = shortcode_atts(array(
            'selectable' => 'false',
            'default' => '10_pages',
            'columns' => '5',
        ), $atts);
        
        $packages = AAP_Settings::get_packages();
        $currency_symbol = AAP_Settings::get_option('currency_symbol', '$');
        $selectable = $atts['selectable'] === 'true';
        
        ob_start();
        ?>
        <div class="aap-pricing-table <?php echo $selectable ? 'aap-pricing-selectable' : ''; ?>">
            <?php foreach ($packages as $key => $package) : 
                $is_popular = ($key === '25_pages');
                $is_selected = ($key === $atts['default']);
            ?>
            <div class="aap-pricing-card <?php echo $is_popular ? 'aap-popular' : ''; ?> <?php echo $is_selected ? 'aap-selected' : ''; ?>"
                 data-package="<?php echo esc_attr($key); ?>"
                 data-price="<?php echo esc_attr($package['price']); ?>"
                 data-pages="<?php echo esc_attr($package['pages']); ?>"
                 data-name="<?php echo esc_attr($package['name']); ?>">
                
                <?php if ($is_popular) : ?>
                <div class="aap-popular-badge">Most Popular</div>
                <?php endif; ?>
                
                <h4 class="aap-package-name"><?php echo esc_html($package['name']); ?></h4>
                
                <div class="aap-package-price">
                    <span class="aap-currency"><?php echo esc_html($currency_symbol); ?></span>
                    <span class="aap-amount"><?php echo esc_html(number_format($package['price'], 0)); ?></span>
                </div>
                
                <div class="aap-package-pages">
                    <strong><?php echo esc_html($package['pages']); ?></strong> pages scanned
                </div>
                
                <ul class="aap-package-features">
                    <li>Full WCAG 2.1 Check</li>
                    <li>Multi-device Screenshots</li>
                    <li>PDF Report</li>
                    <li>Email Delivery</li>
                    <?php if ($package['pages'] >= 25) : ?>
                    <li>Priority Support</li>
                    <?php endif; ?>
                    <?php if ($package['pages'] >= 50) : ?>
                    <li>Detailed Recommendations</li>
                    <?php endif; ?>
                </ul>
                
                <?php if ($selectable) : ?>
                <button type="button" class="aap-button aap-button-select <?php echo $is_selected ? 'aap-button-selected' : ''; ?>">
                    <?php echo $is_selected ? 'Selected' : 'Select'; ?>
                </button>
                <?php else : ?>
                <a href="#aap-order-form" class="aap-button aap-button-primary">Choose Plan</a>
                <?php endif; ?>
                
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Preview scanner shortcode
     */
    public static function render_preview_scanner($atts) {
        $atts = shortcode_atts(array(
            'show_cta' => 'true',
        ), $atts);
        
        ob_start();
        ?>
        <div class="aap-preview-scanner" data-nonce="<?php echo wp_create_nonce('aap_frontend_nonce'); ?>">
            <div class="aap-preview-header">
                <h3>Free Accessibility Preview</h3>
                <p>Get a quick snapshot of your website's accessibility status.</p>
            </div>
            
            <form class="aap-preview-form" id="aap-standalone-preview-form">
                <div class="aap-form-row">
                    <input type="url" 
                           name="preview_url" 
                           placeholder="https://yourwebsite.com" 
                           required 
                           class="aap-input aap-input-large">
                    <button type="submit" class="aap-button aap-button-primary">
                        <span class="aap-button-text">Scan Now</span>
                        <span class="aap-spinner" style="display: none;"></span>
                    </button>
                </div>
                <p class="aap-preview-note">
                    <small>Free preview shows up to 5 issues. Get a full report for complete results.</small>
                </p>
            </form>
            
            <div class="aap-preview-results" id="aap-standalone-preview-results" style="display: none;"></div>
            
            <?php if ($atts['show_cta'] === 'true') : ?>
            <div class="aap-preview-cta" id="aap-preview-cta" style="display: none;">
                <a href="<?php echo esc_url(home_url('/accessibility-audit/')); ?>" class="aap-button aap-button-primary">
                    Get Full Report
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Report status shortcode
     */
    public static function render_report_status($atts) {
        $atts = shortcode_atts(array(
            'report_id' => 0,
        ), $atts);
        
        $report_id = intval($atts['report_id'] ?: ($_GET['id'] ?? 0));
        
        if (!$report_id) {
            return '<div class="aap-notice aap-notice-error">No report ID provided.</div>';
        }
        
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return '<div class="aap-notice aap-notice-error">Report not found.</div>';
        }
        
        $report_data = maybe_unserialize($report['report_data']);
        $summary = $report_data['summary'] ?? array();
        
        ob_start();
        ?>
        <div class="aap-report-status" 
             data-report-id="<?php echo esc_attr($report_id); ?>"
             data-access-key="<?php echo esc_attr($report['access_key'] ?? ''); ?>"
             data-status="<?php echo esc_attr($report['status']); ?>">
            
            <div class="aap-status-header">
                <h2>Accessibility Audit Report</h2>
                <p class="aap-report-url"><?php echo esc_html($report['website_url']); ?></p>
            </div>
            
            <?php if ($report['status'] === 'completed') : ?>
            
            <!-- Completed Report -->
            <div class="aap-status-complete">
                <div class="aap-score-display">
                    <div class="aap-score-circle <?php echo self::get_score_class($summary['score'] ?? 0); ?>">
                        <span class="aap-score-value"><?php echo number_format($summary['score'] ?? 0, 1); ?></span>
                        <span class="aap-score-label">out of 10</span>
                    </div>
                </div>
                
                <div class="aap-stats-grid">
                    <div class="aap-stat">
                        <span class="aap-stat-value"><?php echo intval($summary['total_pages'] ?? 0); ?></span>
                        <span class="aap-stat-label">Pages Scanned</span>
                    </div>
                    <div class="aap-stat aap-stat-error">
                        <span class="aap-stat-value"><?php echo intval($summary['errors'] ?? 0); ?></span>
                        <span class="aap-stat-label">Errors</span>
                    </div>
                    <div class="aap-stat aap-stat-warning">
                        <span class="aap-stat-value"><?php echo intval($summary['warnings'] ?? 0); ?></span>
                        <span class="aap-stat-label">Warnings</span>
                    </div>
                    <div class="aap-stat aap-stat-success">
                        <span class="aap-stat-value"><?php echo intval($summary['passed'] ?? 0); ?></span>
                        <span class="aap-stat-label">Passed</span>
                    </div>
                </div>
                
                <div class="aap-download-section">
                    <a href="<?php echo esc_url(AAP_PDF_Generator::get_download_url($report_id)); ?>" 
                       class="aap-button aap-button-primary aap-button-large">
                        Download PDF Report
                    </a>
                </div>
            </div>
            
            <?php elseif ($report['status'] === 'failed') : ?>
            
            <!-- Failed Report -->
            <div class="aap-status-failed">
                <div class="aap-status-icon aap-status-error">✗</div>
                <h3>Scan Failed</h3>
                <p>We encountered an issue while scanning your website. Our team has been notified and will investigate.</p>
                <p>You should receive an email with more details shortly.</p>
            </div>
            
            <?php else : ?>
            
            <!-- In Progress -->
            <div class="aap-status-progress">
                <div class="aap-progress-animation">
                    <div class="aap-scanner-icon"></div>
                </div>
                
                <h3 class="aap-status-message"><?php echo esc_html(self::get_status_message($report['status'])); ?></h3>
                
                <div class="aap-progress-bar">
                    <div class="aap-progress-fill" style="width: <?php echo intval($report['progress'] ?? 0); ?>%"></div>
                </div>
                
                <p class="aap-progress-text">
                    <?php echo intval($report['progress'] ?? 0); ?>% Complete
                </p>
                
                <p class="aap-status-note">
                    <small>This page will automatically update. You'll also receive an email when your report is ready.</small>
                </p>
            </div>
            
            <?php endif; ?>
            
            <div class="aap-report-meta">
                <p>
                    <strong>Package:</strong> <?php echo esc_html(ucfirst(str_replace('_', ' ', $report['package_type']))); ?><br>
                    <strong>Ordered:</strong> <?php echo esc_html(date('F j, Y g:i a', strtotime($report['created_at']))); ?>
                </p>
            </div>
            
        </div>
        <?php
        return ob_get_clean();
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
    
    /**
     * Get status message
     */
    private static function get_status_message($status) {
        $messages = array(
            'pending' => 'Your scan is queued...',
            'scanning' => 'Scanning your website...',
            'processing' => 'Processing results...',
            'generating' => 'Generating your report...',
        );
        
        return $messages[$status] ?? 'Processing...';
    }
}

// Initialize shortcodes
AAP_Shortcodes::init();
