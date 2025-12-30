<?php
/**
 * Settings Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting('aap_general_settings', 'aap_company_name');
        register_setting('aap_general_settings', 'aap_company_logo');
        register_setting('aap_general_settings', 'aap_company_email');
        
        // Pricing Settings
        register_setting('aap_pricing_settings', 'aap_price_5_pages');
        register_setting('aap_pricing_settings', 'aap_price_10_pages');
        register_setting('aap_pricing_settings', 'aap_price_25_pages');
        register_setting('aap_pricing_settings', 'aap_price_50_pages');
        register_setting('aap_pricing_settings', 'aap_price_100_pages');
        register_setting('aap_pricing_settings', 'aap_currency');
        
        // PayPal Settings
        register_setting('aap_paypal_settings', 'aap_paypal_mode');
        register_setting('aap_paypal_settings', 'aap_paypal_client_id');
        register_setting('aap_paypal_settings', 'aap_paypal_client_secret');
        
        // Email Settings
        register_setting('aap_email_settings', 'aap_email_from_name');
        register_setting('aap_email_settings', 'aap_email_from_address');
        register_setting('aap_email_settings', 'aap_email_subject');
        register_setting('aap_email_settings', 'aap_email_template');
        
        // Access Settings
        register_setting('aap_access_settings', 'aap_free_access_roles');
        
        // Branding Settings
        register_setting('aap_branding_settings', 'aap_report_header_color');
        register_setting('aap_branding_settings', 'aap_report_accent_color');
        register_setting('aap_branding_settings', 'aap_show_powered_by');
    }
    
    /**
     * Get option with default
     */
    public static function get_option($key, $default = '') {
        $value = get_option('aap_' . $key, $default);
        return $value !== false ? $value : $default;
    }
    
    /**
     * Update option
     */
    public static function update_option($key, $value) {
        return update_option('aap_' . $key, $value);
    }
    
    /**
     * Get pricing packages
     */
    public static function get_packages() {
        $currency = self::get_option('currency', 'USD');
        $currency_symbol = self::get_currency_symbol($currency);
        
        return array(
            '5_pages' => array(
                'name' => __('Starter', 'accessibility-audit-pro'),
                'pages' => 5,
                'price' => floatval(self::get_option('price_5_pages', '29.00')),
                'price_display' => $currency_symbol . self::get_option('price_5_pages', '29.00'),
                'description' => __('Perfect for small websites and landing pages', 'accessibility-audit-pro'),
            ),
            '10_pages' => array(
                'name' => __('Basic', 'accessibility-audit-pro'),
                'pages' => 10,
                'price' => floatval(self::get_option('price_10_pages', '49.00')),
                'price_display' => $currency_symbol . self::get_option('price_10_pages', '49.00'),
                'description' => __('Ideal for small business websites', 'accessibility-audit-pro'),
            ),
            '25_pages' => array(
                'name' => __('Professional', 'accessibility-audit-pro'),
                'pages' => 25,
                'price' => floatval(self::get_option('price_25_pages', '99.00')),
                'price_display' => $currency_symbol . self::get_option('price_25_pages', '99.00'),
                'description' => __('Great for medium-sized websites', 'accessibility-audit-pro'),
                'popular' => true,
            ),
            '50_pages' => array(
                'name' => __('Business', 'accessibility-audit-pro'),
                'pages' => 50,
                'price' => floatval(self::get_option('price_50_pages', '179.00')),
                'price_display' => $currency_symbol . self::get_option('price_50_pages', '179.00'),
                'description' => __('Comprehensive audit for larger websites', 'accessibility-audit-pro'),
            ),
            '100_pages' => array(
                'name' => __('Enterprise', 'accessibility-audit-pro'),
                'pages' => 100,
                'price' => floatval(self::get_option('price_100_pages', '299.00')),
                'price_display' => $currency_symbol . self::get_option('price_100_pages', '299.00'),
                'description' => __('Full audit for enterprise websites', 'accessibility-audit-pro'),
            ),
        );
    }
    
    /**
     * Get currency symbol
     */
    public static function get_currency_symbol($currency) {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'JPY' => '¥',
            'INR' => '₹',
        );
        
        return isset($symbols[$currency]) ? $symbols[$currency] : '$';
    }
    
    /**
     * Get WCAG criteria
     */
    public static function get_wcag_criteria() {
        return array(
            // Perceivable
            '1.1.1' => array(
                'name' => 'Non-text Content',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'All non-text content has a text alternative.',
            ),
            '1.2.1' => array(
                'name' => 'Audio-only and Video-only',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'Alternatives provided for audio-only and video-only content.',
            ),
            '1.2.2' => array(
                'name' => 'Captions',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'Captions are provided for all prerecorded audio content.',
            ),
            '1.3.1' => array(
                'name' => 'Info and Relationships',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'Information and relationships conveyed through presentation can be programmatically determined.',
            ),
            '1.3.2' => array(
                'name' => 'Meaningful Sequence',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'Content is presented in a meaningful sequence.',
            ),
            '1.3.3' => array(
                'name' => 'Sensory Characteristics',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'Instructions do not rely solely on sensory characteristics.',
            ),
            '1.4.1' => array(
                'name' => 'Use of Color',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'Color is not the only visual means of conveying information.',
            ),
            '1.4.2' => array(
                'name' => 'Audio Control',
                'level' => 'A',
                'principle' => 'Perceivable',
                'description' => 'Audio that plays automatically can be paused or stopped.',
            ),
            '1.4.3' => array(
                'name' => 'Contrast (Minimum)',
                'level' => 'AA',
                'principle' => 'Perceivable',
                'description' => 'Text has a contrast ratio of at least 4.5:1.',
            ),
            '1.4.4' => array(
                'name' => 'Resize Text',
                'level' => 'AA',
                'principle' => 'Perceivable',
                'description' => 'Text can be resized up to 200% without loss of content.',
            ),
            '1.4.5' => array(
                'name' => 'Images of Text',
                'level' => 'AA',
                'principle' => 'Perceivable',
                'description' => 'Text is used instead of images of text where possible.',
            ),
            
            // Operable
            '2.1.1' => array(
                'name' => 'Keyboard',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'All functionality is operable through keyboard.',
            ),
            '2.1.2' => array(
                'name' => 'No Keyboard Trap',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'Keyboard focus can be moved away from any component.',
            ),
            '2.2.1' => array(
                'name' => 'Timing Adjustable',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'Time limits can be adjusted, extended, or turned off.',
            ),
            '2.2.2' => array(
                'name' => 'Pause, Stop, Hide',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'Moving, blinking content can be paused, stopped, or hidden.',
            ),
            '2.3.1' => array(
                'name' => 'Three Flashes',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'Nothing flashes more than three times per second.',
            ),
            '2.4.1' => array(
                'name' => 'Bypass Blocks',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'A mechanism is available to bypass repeated content.',
            ),
            '2.4.2' => array(
                'name' => 'Page Titled',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'Web pages have titles that describe topic or purpose.',
            ),
            '2.4.3' => array(
                'name' => 'Focus Order',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'Components receive focus in a meaningful sequence.',
            ),
            '2.4.4' => array(
                'name' => 'Link Purpose (In Context)',
                'level' => 'A',
                'principle' => 'Operable',
                'description' => 'Purpose of links can be determined from link text or context.',
            ),
            '2.4.5' => array(
                'name' => 'Multiple Ways',
                'level' => 'AA',
                'principle' => 'Operable',
                'description' => 'Multiple ways to locate a web page are available.',
            ),
            '2.4.6' => array(
                'name' => 'Headings and Labels',
                'level' => 'AA',
                'principle' => 'Operable',
                'description' => 'Headings and labels describe topic or purpose.',
            ),
            '2.4.7' => array(
                'name' => 'Focus Visible',
                'level' => 'AA',
                'principle' => 'Operable',
                'description' => 'Keyboard focus indicator is visible.',
            ),
            
            // Understandable
            '3.1.1' => array(
                'name' => 'Language of Page',
                'level' => 'A',
                'principle' => 'Understandable',
                'description' => 'Default language of page is programmatically determined.',
            ),
            '3.1.2' => array(
                'name' => 'Language of Parts',
                'level' => 'AA',
                'principle' => 'Understandable',
                'description' => 'Language of content passages is programmatically determined.',
            ),
            '3.2.1' => array(
                'name' => 'On Focus',
                'level' => 'A',
                'principle' => 'Understandable',
                'description' => 'Receiving focus does not trigger a change of context.',
            ),
            '3.2.2' => array(
                'name' => 'On Input',
                'level' => 'A',
                'principle' => 'Understandable',
                'description' => 'Changing a setting does not automatically cause a change of context.',
            ),
            '3.2.3' => array(
                'name' => 'Consistent Navigation',
                'level' => 'AA',
                'principle' => 'Understandable',
                'description' => 'Navigation is consistent across pages.',
            ),
            '3.2.4' => array(
                'name' => 'Consistent Identification',
                'level' => 'AA',
                'principle' => 'Understandable',
                'description' => 'Components with same functionality are identified consistently.',
            ),
            '3.3.1' => array(
                'name' => 'Error Identification',
                'level' => 'A',
                'principle' => 'Understandable',
                'description' => 'Input errors are automatically identified and described.',
            ),
            '3.3.2' => array(
                'name' => 'Labels or Instructions',
                'level' => 'A',
                'principle' => 'Understandable',
                'description' => 'Labels or instructions are provided for user input.',
            ),
            '3.3.3' => array(
                'name' => 'Error Suggestion',
                'level' => 'AA',
                'principle' => 'Understandable',
                'description' => 'Suggestions are provided when errors are detected.',
            ),
            '3.3.4' => array(
                'name' => 'Error Prevention',
                'level' => 'AA',
                'principle' => 'Understandable',
                'description' => 'Submissions can be reviewed, confirmed, or reversed.',
            ),
            
            // Robust
            '4.1.1' => array(
                'name' => 'Parsing',
                'level' => 'A',
                'principle' => 'Robust',
                'description' => 'Content can be reliably interpreted by user agents.',
            ),
            '4.1.2' => array(
                'name' => 'Name, Role, Value',
                'level' => 'A',
                'principle' => 'Robust',
                'description' => 'UI components have accessible names and roles.',
            ),
        );
    }
}
