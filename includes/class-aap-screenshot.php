<?php
/**
 * Screenshot Capture Service
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Screenshot {
    
    /**
     * Device configurations
     */
    private static $devices = array(
        'desktop' => array(
            'width' => 1920,
            'height' => 1080,
            'device_scale_factor' => 1,
            'is_mobile' => false,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ),
        'tablet' => array(
            'width' => 768,
            'height' => 1024,
            'device_scale_factor' => 2,
            'is_mobile' => true,
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ),
        'mobile' => array(
            'width' => 375,
            'height' => 812,
            'device_scale_factor' => 3,
            'is_mobile' => true,
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ),
    );
    
    /**
     * Capture screenshot
     */
    public static function capture($url, $device = 'desktop', $report_id = '') {
        $provider = AAP_Settings::get_option('screenshot_api_provider', 'internal');
        
        switch ($provider) {
            case 'screenshotmachine':
                return self::capture_screenshotmachine($url, $device, $report_id);
            case 'screenshotlayer':
                return self::capture_screenshotlayer($url, $device, $report_id);
            case 'apiflash':
                return self::capture_apiflash($url, $device, $report_id);
            default:
                return self::capture_internal($url, $device, $report_id);
        }
    }
    
    /**
     * Internal capture using WordPress HTTP API and placeholder
     * Note: For production, use a proper screenshot API service
     */
    private static function capture_internal($url, $device, $report_id) {
        $upload_dir = wp_upload_dir();
        $screenshots_dir = $upload_dir['basedir'] . '/accessibility-audit-pro/screenshots';
        
        // Ensure directory exists
        if (!file_exists($screenshots_dir)) {
            wp_mkdir_p($screenshots_dir);
        }
        
        $filename = sanitize_file_name($report_id . '-' . $device . '-' . time() . '.png');
        $filepath = $screenshots_dir . '/' . $filename;
        
        // Try to use Google PageSpeed Insights API for screenshot (free)
        $viewport = self::$devices[$device];
        $strategy = $viewport['is_mobile'] ? 'mobile' : 'desktop';
        
        $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
        $api_url .= '?url=' . urlencode($url);
        $api_url .= '&strategy=' . $strategy;
        $api_url .= '&category=accessibility';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 60,
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['lighthouseResult']['audits']['final-screenshot']['details']['data'])) {
                $screenshot_data = $body['lighthouseResult']['audits']['final-screenshot']['details']['data'];
                
                // Remove data URL prefix
                $screenshot_data = preg_replace('/^data:image\/\w+;base64,/', '', $screenshot_data);
                $screenshot_binary = base64_decode($screenshot_data);
                
                if ($screenshot_binary !== false) {
                    file_put_contents($filepath, $screenshot_binary);
                    return $filepath;
                }
            }
        }
        
        // Fallback: Create placeholder image
        return self::create_placeholder_screenshot($filepath, $url, $device);
    }
    
    /**
     * Create placeholder screenshot with URL info
     */
    private static function create_placeholder_screenshot($filepath, $url, $device) {
        $viewport = self::$devices[$device];
        $width = min($viewport['width'], 800);
        $height = min($viewport['height'], 600);
        
        // Create image
        $image = imagecreatetruecolor($width, $height);
        
        // Colors
        $bg_color = imagecolorallocate($image, 245, 245, 245);
        $text_color = imagecolorallocate($image, 51, 51, 51);
        $accent_color = imagecolorallocate($image, 7, 89, 156);
        $border_color = imagecolorallocate($image, 200, 200, 200);
        
        // Fill background
        imagefill($image, 0, 0, $bg_color);
        
        // Draw border
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $border_color);
        
        // Draw device icon area
        $icon_y = $height / 3;
        
        // Device indicator
        $device_label = ucfirst($device) . ' View';
        $device_dims = $viewport['width'] . 'x' . $viewport['height'];
        
        // Draw text
        $font_size = 4;
        
        imagestring($image, 5, ($width - strlen($device_label) * 9) / 2, $icon_y, $device_label, $accent_color);
        imagestring($image, 3, ($width - strlen($device_dims) * 7) / 2, $icon_y + 25, $device_dims, $text_color);
        
        // URL
        $short_url = strlen($url) > 50 ? substr($url, 0, 47) . '...' : $url;
        imagestring($image, 2, ($width - strlen($short_url) * 6) / 2, $icon_y + 60, $short_url, $text_color);
        
        // Note
        $note = 'Screenshot captured during scan';
        imagestring($image, 2, ($width - strlen($note) * 6) / 2, $height - 40, $note, $border_color);
        
        // Save
        imagepng($image, $filepath);
        imagedestroy($image);
        
        return $filepath;
    }
    
    /**
     * Capture using ScreenshotMachine API
     */
    private static function capture_screenshotmachine($url, $device, $report_id) {
        $api_key = AAP_Settings::get_option('screenshot_api_key');
        
        if (empty($api_key)) {
            return self::capture_internal($url, $device, $report_id);
        }
        
        $upload_dir = wp_upload_dir();
        $screenshots_dir = $upload_dir['basedir'] . '/accessibility-audit-pro/screenshots';
        
        if (!file_exists($screenshots_dir)) {
            wp_mkdir_p($screenshots_dir);
        }
        
        $filename = sanitize_file_name($report_id . '-' . $device . '-' . time() . '.png');
        $filepath = $screenshots_dir . '/' . $filename;
        
        $viewport = self::$devices[$device];
        
        $api_url = 'https://api.screenshotmachine.com/';
        $api_url .= '?key=' . $api_key;
        $api_url .= '&url=' . urlencode($url);
        $api_url .= '&dimension=' . $viewport['width'] . 'x' . $viewport['height'];
        $api_url .= '&device=' . ($viewport['is_mobile'] ? 'phone' : 'desktop');
        $api_url .= '&format=png';
        $api_url .= '&cacheLimit=0';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 60,
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $image_data = wp_remote_retrieve_body($response);
            file_put_contents($filepath, $image_data);
            return $filepath;
        }
        
        return self::capture_internal($url, $device, $report_id);
    }
    
    /**
     * Capture using Screenshotlayer API
     */
    private static function capture_screenshotlayer($url, $device, $report_id) {
        $api_key = AAP_Settings::get_option('screenshot_api_key');
        
        if (empty($api_key)) {
            return self::capture_internal($url, $device, $report_id);
        }
        
        $upload_dir = wp_upload_dir();
        $screenshots_dir = $upload_dir['basedir'] . '/accessibility-audit-pro/screenshots';
        
        if (!file_exists($screenshots_dir)) {
            wp_mkdir_p($screenshots_dir);
        }
        
        $filename = sanitize_file_name($report_id . '-' . $device . '-' . time() . '.png');
        $filepath = $screenshots_dir . '/' . $filename;
        
        $viewport = self::$devices[$device];
        
        $api_url = 'http://api.screenshotlayer.com/api/capture';
        $api_url .= '?access_key=' . $api_key;
        $api_url .= '&url=' . urlencode($url);
        $api_url .= '&viewport=' . $viewport['width'] . 'x' . $viewport['height'];
        $api_url .= '&format=PNG';
        $api_url .= '&force=1';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 60,
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $image_data = wp_remote_retrieve_body($response);
            
            // Check if it's actually an image (not JSON error)
            if (strpos($image_data, 'PNG') !== false || strpos($image_data, 'IHDR') !== false) {
                file_put_contents($filepath, $image_data);
                return $filepath;
            }
        }
        
        return self::capture_internal($url, $device, $report_id);
    }
    
    /**
     * Capture using ApiFlash API
     */
    private static function capture_apiflash($url, $device, $report_id) {
        $api_key = AAP_Settings::get_option('screenshot_api_key');
        
        if (empty($api_key)) {
            return self::capture_internal($url, $device, $report_id);
        }
        
        $upload_dir = wp_upload_dir();
        $screenshots_dir = $upload_dir['basedir'] . '/accessibility-audit-pro/screenshots';
        
        if (!file_exists($screenshots_dir)) {
            wp_mkdir_p($screenshots_dir);
        }
        
        $filename = sanitize_file_name($report_id . '-' . $device . '-' . time() . '.png');
        $filepath = $screenshots_dir . '/' . $filename;
        
        $viewport = self::$devices[$device];
        
        $api_url = 'https://api.apiflash.com/v1/urltoimage';
        $api_url .= '?access_key=' . $api_key;
        $api_url .= '&url=' . urlencode($url);
        $api_url .= '&width=' . $viewport['width'];
        $api_url .= '&height=' . $viewport['height'];
        $api_url .= '&format=png';
        $api_url .= '&fresh=true';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 60,
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $image_data = wp_remote_retrieve_body($response);
            file_put_contents($filepath, $image_data);
            return $filepath;
        }
        
        return self::capture_internal($url, $device, $report_id);
    }
    
    /**
     * Get screenshot URL from path
     */
    public static function get_url($filepath) {
        if (empty($filepath) || !file_exists($filepath)) {
            return '';
        }
        
        $upload_dir = wp_upload_dir();
        $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath);
        
        return $url;
    }
    
    /**
     * Delete screenshot file
     */
    public static function delete($filepath) {
        if (!empty($filepath) && file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
    
    /**
     * Get available screenshot providers
     */
    public static function get_providers() {
        return array(
            'internal' => array(
                'name' => __('Built-in (Google PageSpeed)', 'accessibility-audit-pro'),
                'description' => __('Uses Google PageSpeed API - Free but limited quality', 'accessibility-audit-pro'),
                'requires_key' => false,
            ),
            'screenshotmachine' => array(
                'name' => __('Screenshot Machine', 'accessibility-audit-pro'),
                'description' => __('Professional screenshot API service', 'accessibility-audit-pro'),
                'requires_key' => true,
                'signup_url' => 'https://www.screenshotmachine.com/',
            ),
            'screenshotlayer' => array(
                'name' => __('Screenshotlayer', 'accessibility-audit-pro'),
                'description' => __('Simple screenshot API with free tier', 'accessibility-audit-pro'),
                'requires_key' => true,
                'signup_url' => 'https://screenshotlayer.com/',
            ),
            'apiflash' => array(
                'name' => __('ApiFlash', 'accessibility-audit-pro'),
                'description' => __('High-quality screenshots with Chrome', 'accessibility-audit-pro'),
                'requires_key' => true,
                'signup_url' => 'https://apiflash.com/',
            ),
        );
    }
}
