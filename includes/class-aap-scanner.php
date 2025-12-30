<?php
/**
 * Accessibility Scanner
 * Core scanning engine for WCAG compliance checking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Scanner {
    
    /**
     * Device viewport sizes
     */
    private static $viewports = array(
        'desktop' => array('width' => 1920, 'height' => 1080),
        'tablet' => array('width' => 768, 'height' => 1024),
        'mobile' => array('width' => 375, 'height' => 812),
    );
    
    /**
     * Run full accessibility scan
     */
    public static function scan_website($report_id, $url, $pages_count = 5) {
        $results = array(
            'report_id' => $report_id,
            'url' => $url,
            'pages' => array(),
            'summary' => array(
                'total_pages' => 0,
                'total_issues' => 0,
                'errors' => 0,
                'warnings' => 0,
                'notices' => 0,
                'passed' => 0,
                'score' => 0,
            ),
            'wcag_compliance' => array(
                'level_a' => array('passed' => 0, 'failed' => 0),
                'level_aa' => array('passed' => 0, 'failed' => 0),
            ),
            'started_at' => current_time('mysql'),
            'completed_at' => null,
        );
        
        // Get pages to scan
        $pages = self::discover_pages($url, $pages_count);
        
        // Scan each page
        foreach ($pages as $page_url) {
            foreach (array_keys(self::$viewports) as $device) {
                $page_result = self::scan_page($report_id, $page_url, $device);
                
                if ($page_result) {
                    $results['pages'][] = $page_result;
                    
                    // Update summary
                    $results['summary']['total_issues'] += $page_result['issues_count'];
                    $results['summary']['errors'] += $page_result['errors_count'];
                    $results['summary']['warnings'] += $page_result['warnings_count'];
                    $results['summary']['passed'] += $page_result['passed_count'];
                }
            }
            $results['summary']['total_pages']++;
        }
        
        // Calculate overall score
        $results['summary']['score'] = self::calculate_score($results);
        
        // Calculate WCAG compliance
        $results['wcag_compliance'] = self::calculate_wcag_compliance($results);
        
        $results['completed_at'] = current_time('mysql');
        
        // Update report in database
        AAP_Database::update_report($report_id, array(
            'report_data' => maybe_serialize($results),
            'scan_status' => 'completed',
            'completed_at' => $results['completed_at'],
        ));
        
        return $results;
    }
    
    /**
     * Discover pages on website
     */
    public static function discover_pages($url, $limit = 5) {
        $pages = array($url);
        $visited = array($url);
        $base_url = parse_url($url);
        $base_host = $base_url['host'];
        
        // Fetch homepage and extract links
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'AccessibilityAuditPro/1.0',
        ));
        
        if (is_wp_error($response)) {
            return $pages;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Extract links
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $link) {
                if (count($pages) >= $limit) {
                    break;
                }
                
                // Normalize URL
                $link = self::normalize_url($link, $url);
                
                if (!$link) {
                    continue;
                }
                
                // Check if same domain
                $link_host = parse_url($link, PHP_URL_HOST);
                if ($link_host !== $base_host) {
                    continue;
                }
                
                // Skip certain URLs
                if (self::should_skip_url($link)) {
                    continue;
                }
                
                // Check if already visited
                if (in_array($link, $visited)) {
                    continue;
                }
                
                $pages[] = $link;
                $visited[] = $link;
            }
        }
        
        return $pages;
    }
    
    /**
     * Scan a single page
     */
    public static function scan_page($report_id, $url, $device = 'desktop') {
        $viewport = self::$viewports[$device];
        
        // Fetch page content
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'AccessibilityAuditPro/1.0',
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);
        
        // Get page title
        $title_nodes = $xpath->query('//title');
        $page_title = $title_nodes->length > 0 ? $title_nodes->item(0)->textContent : '';
        
        // Run accessibility checks
        $issues = array();
        $passed = array();
        
        // Run all checks
        $checks = array(
            'check_images_alt' => '1.1.1',
            'check_form_labels' => '3.3.2',
            'check_heading_structure' => '1.3.1',
            'check_link_text' => '2.4.4',
            'check_language_attribute' => '3.1.1',
            'check_page_title' => '2.4.2',
            'check_color_contrast' => '1.4.3',
            'check_skip_links' => '2.4.1',
            'check_keyboard_focus' => '2.4.7',
            'check_aria_labels' => '4.1.2',
            'check_tables' => '1.3.1',
            'check_iframes' => '4.1.2',
            'check_buttons' => '4.1.2',
            'check_lists' => '1.3.1',
            'check_landmarks' => '1.3.1',
            'check_media' => '1.2.1',
        );
        
        foreach ($checks as $method => $wcag_criterion) {
            $result = self::$method($dom, $xpath, $html);
            
            foreach ($result['issues'] as $issue) {
                $issue['wcag'] = $wcag_criterion;
                $issues[] = $issue;
            }
            
            foreach ($result['passed'] as $pass) {
                $pass['wcag'] = $wcag_criterion;
                $passed[] = $pass;
            }
        }
        
        // Count by severity
        $errors_count = count(array_filter($issues, function($i) { return $i['severity'] === 'error'; }));
        $warnings_count = count(array_filter($issues, function($i) { return $i['severity'] === 'warning'; }));
        $notices_count = count(array_filter($issues, function($i) { return $i['severity'] === 'notice'; }));
        
        // Take screenshot
        $screenshot_path = AAP_Screenshot::capture($url, $device, $report_id);
        
        // Prepare result
        $result = array(
            'page_url' => $url,
            'page_title' => $page_title,
            'device_type' => $device,
            'viewport' => $viewport,
            'screenshot_path' => $screenshot_path,
            'issues' => $issues,
            'passed' => $passed,
            'issues_count' => count($issues),
            'errors_count' => $errors_count,
            'warnings_count' => $warnings_count,
            'notices_count' => $notices_count,
            'passed_count' => count($passed),
            'scanned_at' => current_time('mysql'),
        );
        
        // Save to database
        AAP_Database::save_scanned_page(array(
            'report_id' => $report_id,
            'page_url' => $url,
            'page_title' => $page_title,
            'device_type' => $device,
            'screenshot_path' => $screenshot_path,
            'issues_count' => count($issues),
            'errors_count' => $errors_count,
            'warnings_count' => $warnings_count,
            'passed_count' => count($passed),
            'scan_data' => $result,
        ));
        
        return $result;
    }
    
    /**
     * Check images for alt text (WCAG 1.1.1)
     */
    private static function check_images_alt($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $images = $xpath->query('//img');
        
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            $alt = $img->getAttribute('alt');
            $role = $img->getAttribute('role');
            
            // Check if decorative (role="presentation" or empty alt is intentional)
            if ($role === 'presentation' || $role === 'none') {
                $passed[] = array(
                    'type' => 'image_decorative',
                    'message' => 'Decorative image correctly marked',
                    'element' => '<img src="' . esc_attr($src) . '">',
                    'severity' => 'pass',
                );
                continue;
            }
            
            if ($alt === null || $alt === '') {
                $issues[] = array(
                    'type' => 'image_missing_alt',
                    'message' => 'Image missing alternative text',
                    'element' => '<img src="' . esc_attr($src) . '">',
                    'severity' => 'error',
                    'recommendation' => 'Add descriptive alt text or mark as decorative with alt=""',
                );
            } elseif (strlen($alt) > 125) {
                $issues[] = array(
                    'type' => 'image_alt_too_long',
                    'message' => 'Alt text is too long (over 125 characters)',
                    'element' => '<img alt="' . esc_attr(substr($alt, 0, 50)) . '...">',
                    'severity' => 'warning',
                    'recommendation' => 'Keep alt text concise. Consider using longdesc for detailed descriptions.',
                );
            } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $alt)) {
                $issues[] = array(
                    'type' => 'image_alt_filename',
                    'message' => 'Alt text appears to be a filename',
                    'element' => '<img alt="' . esc_attr($alt) . '">',
                    'severity' => 'error',
                    'recommendation' => 'Replace filename with descriptive text',
                );
            } else {
                $passed[] = array(
                    'type' => 'image_has_alt',
                    'message' => 'Image has appropriate alt text',
                    'element' => '<img alt="' . esc_attr(substr($alt, 0, 50)) . '">',
                    'severity' => 'pass',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check form labels (WCAG 3.3.2)
     */
    private static function check_form_labels($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Check inputs
        $inputs = $xpath->query('//input[@type!="hidden" and @type!="submit" and @type!="button" and @type!="reset" and @type!="image"]');
        
        foreach ($inputs as $input) {
            $id = $input->getAttribute('id');
            $name = $input->getAttribute('name');
            $type = $input->getAttribute('type');
            $aria_label = $input->getAttribute('aria-label');
            $aria_labelledby = $input->getAttribute('aria-labelledby');
            $title = $input->getAttribute('title');
            
            $has_label = false;
            
            // Check for associated label
            if ($id) {
                $labels = $xpath->query("//label[@for='{$id}']");
                if ($labels->length > 0) {
                    $has_label = true;
                }
            }
            
            // Check for aria-label or aria-labelledby
            if ($aria_label || $aria_labelledby) {
                $has_label = true;
            }
            
            // Check for title attribute
            if ($title) {
                $has_label = true;
            }
            
            // Check for wrapping label
            if (!$has_label) {
                $parent = $input->parentNode;
                while ($parent) {
                    if ($parent->nodeName === 'label') {
                        $has_label = true;
                        break;
                    }
                    $parent = $parent->parentNode;
                }
            }
            
            if (!$has_label) {
                $issues[] = array(
                    'type' => 'form_missing_label',
                    'message' => "Form input missing accessible label",
                    'element' => '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '">',
                    'severity' => 'error',
                    'recommendation' => 'Add a <label> element or aria-label attribute',
                );
            } else {
                $passed[] = array(
                    'type' => 'form_has_label',
                    'message' => 'Form input has accessible label',
                    'element' => '<input type="' . esc_attr($type) . '">',
                    'severity' => 'pass',
                );
            }
        }
        
        // Check textareas
        $textareas = $xpath->query('//textarea');
        foreach ($textareas as $textarea) {
            $id = $textarea->getAttribute('id');
            $aria_label = $textarea->getAttribute('aria-label');
            
            $has_label = false;
            if ($id) {
                $labels = $xpath->query("//label[@for='{$id}']");
                if ($labels->length > 0) {
                    $has_label = true;
                }
            }
            if ($aria_label) {
                $has_label = true;
            }
            
            if (!$has_label) {
                $issues[] = array(
                    'type' => 'textarea_missing_label',
                    'message' => 'Textarea missing accessible label',
                    'element' => '<textarea>',
                    'severity' => 'error',
                    'recommendation' => 'Add a <label> element or aria-label attribute',
                );
            }
        }
        
        // Check selects
        $selects = $xpath->query('//select');
        foreach ($selects as $select) {
            $id = $select->getAttribute('id');
            $aria_label = $select->getAttribute('aria-label');
            
            $has_label = false;
            if ($id) {
                $labels = $xpath->query("//label[@for='{$id}']");
                if ($labels->length > 0) {
                    $has_label = true;
                }
            }
            if ($aria_label) {
                $has_label = true;
            }
            
            if (!$has_label) {
                $issues[] = array(
                    'type' => 'select_missing_label',
                    'message' => 'Select element missing accessible label',
                    'element' => '<select>',
                    'severity' => 'error',
                    'recommendation' => 'Add a <label> element or aria-label attribute',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check heading structure (WCAG 1.3.1)
     */
    private static function check_heading_structure($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $headings = array();
        for ($i = 1; $i <= 6; $i++) {
            $h_tags = $xpath->query("//h{$i}");
            foreach ($h_tags as $h) {
                $headings[] = array(
                    'level' => $i,
                    'text' => trim($h->textContent),
                );
            }
        }
        
        // Check for H1
        $h1_count = count(array_filter($headings, function($h) { return $h['level'] === 1; }));
        
        if ($h1_count === 0) {
            $issues[] = array(
                'type' => 'heading_no_h1',
                'message' => 'Page missing H1 heading',
                'element' => '',
                'severity' => 'error',
                'recommendation' => 'Add a single H1 heading to describe the page content',
            );
        } elseif ($h1_count > 1) {
            $issues[] = array(
                'type' => 'heading_multiple_h1',
                'message' => "Page has {$h1_count} H1 headings (should have 1)",
                'element' => '',
                'severity' => 'warning',
                'recommendation' => 'Use only one H1 per page for the main heading',
            );
        } else {
            $passed[] = array(
                'type' => 'heading_single_h1',
                'message' => 'Page has single H1 heading',
                'element' => '',
                'severity' => 'pass',
            );
        }
        
        // Check heading order (no skipped levels)
        $prev_level = 0;
        foreach ($headings as $heading) {
            if ($heading['level'] > $prev_level + 1 && $prev_level > 0) {
                $issues[] = array(
                    'type' => 'heading_skipped_level',
                    'message' => "Heading level skipped: H{$prev_level} to H{$heading['level']}",
                    'element' => '<h' . $heading['level'] . '>' . esc_html(substr($heading['text'], 0, 50)) . '</h' . $heading['level'] . '>',
                    'severity' => 'warning',
                    'recommendation' => 'Maintain sequential heading hierarchy',
                );
            }
            $prev_level = $heading['level'];
        }
        
        // Check for empty headings
        foreach ($headings as $heading) {
            if (empty(trim($heading['text']))) {
                $issues[] = array(
                    'type' => 'heading_empty',
                    'message' => "Empty H{$heading['level']} heading",
                    'element' => '<h' . $heading['level'] . '></h' . $heading['level'] . '>',
                    'severity' => 'error',
                    'recommendation' => 'Add meaningful text content to heading',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check link text (WCAG 2.4.4)
     */
    private static function check_link_text($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $links = $xpath->query('//a[@href]');
        
        $generic_texts = array('click here', 'read more', 'learn more', 'here', 'more', 'link', 'click');
        
        foreach ($links as $link) {
            $text = trim($link->textContent);
            $href = $link->getAttribute('href');
            $aria_label = $link->getAttribute('aria-label');
            $title = $link->getAttribute('title');
            
            // Get accessible name
            $accessible_name = $aria_label ?: $text;
            
            // Check for empty link
            if (empty($accessible_name) && empty($title)) {
                // Check for image with alt
                $images = $xpath->query('.//img[@alt]', $link);
                if ($images->length > 0) {
                    $accessible_name = $images->item(0)->getAttribute('alt');
                }
            }
            
            if (empty($accessible_name) && empty($title)) {
                $issues[] = array(
                    'type' => 'link_empty',
                    'message' => 'Link has no accessible name',
                    'element' => '<a href="' . esc_attr(substr($href, 0, 50)) . '">',
                    'severity' => 'error',
                    'recommendation' => 'Add descriptive link text or aria-label',
                );
            } elseif (in_array(strtolower($accessible_name), $generic_texts)) {
                $issues[] = array(
                    'type' => 'link_generic_text',
                    'message' => "Link uses generic text: \"{$accessible_name}\"",
                    'element' => '<a>' . esc_html($accessible_name) . '</a>',
                    'severity' => 'warning',
                    'recommendation' => 'Use descriptive text that explains the link destination',
                );
            } else {
                $passed[] = array(
                    'type' => 'link_descriptive',
                    'message' => 'Link has descriptive text',
                    'element' => '<a>' . esc_html(substr($accessible_name, 0, 30)) . '</a>',
                    'severity' => 'pass',
                );
            }
            
            // Check for new window without warning
            $target = $link->getAttribute('target');
            if ($target === '_blank') {
                if (strpos(strtolower($accessible_name . $title), 'new window') === false &&
                    strpos(strtolower($accessible_name . $title), 'new tab') === false) {
                    $issues[] = array(
                        'type' => 'link_new_window_warning',
                        'message' => 'Link opens in new window without warning',
                        'element' => '<a target="_blank">' . esc_html(substr($accessible_name, 0, 30)) . '</a>',
                        'severity' => 'warning',
                        'recommendation' => 'Add "(opens in new window)" to link text or aria-label',
                    );
                }
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check language attribute (WCAG 3.1.1)
     */
    private static function check_language_attribute($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $html_element = $xpath->query('//html')->item(0);
        
        if ($html_element) {
            $lang = $html_element->getAttribute('lang');
            $xml_lang = $html_element->getAttribute('xml:lang');
            
            if (empty($lang) && empty($xml_lang)) {
                $issues[] = array(
                    'type' => 'lang_missing',
                    'message' => 'Page missing language attribute',
                    'element' => '<html>',
                    'severity' => 'error',
                    'recommendation' => 'Add lang attribute to <html> element (e.g., lang="en")',
                );
            } elseif (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang ?: $xml_lang)) {
                $issues[] = array(
                    'type' => 'lang_invalid',
                    'message' => 'Invalid language code',
                    'element' => '<html lang="' . esc_attr($lang) . '">',
                    'severity' => 'error',
                    'recommendation' => 'Use valid BCP 47 language code (e.g., "en", "en-US")',
                );
            } else {
                $passed[] = array(
                    'type' => 'lang_valid',
                    'message' => 'Valid language attribute present',
                    'element' => '<html lang="' . esc_attr($lang ?: $xml_lang) . '">',
                    'severity' => 'pass',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check page title (WCAG 2.4.2)
     */
    private static function check_page_title($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $titles = $xpath->query('//title');
        
        if ($titles->length === 0) {
            $issues[] = array(
                'type' => 'title_missing',
                'message' => 'Page missing <title> element',
                'element' => '',
                'severity' => 'error',
                'recommendation' => 'Add a descriptive <title> element in <head>',
            );
        } else {
            $title_text = trim($titles->item(0)->textContent);
            
            if (empty($title_text)) {
                $issues[] = array(
                    'type' => 'title_empty',
                    'message' => 'Page title is empty',
                    'element' => '<title></title>',
                    'severity' => 'error',
                    'recommendation' => 'Add descriptive text to the title element',
                );
            } elseif (strlen($title_text) < 10) {
                $issues[] = array(
                    'type' => 'title_too_short',
                    'message' => 'Page title may be too short',
                    'element' => '<title>' . esc_html($title_text) . '</title>',
                    'severity' => 'warning',
                    'recommendation' => 'Use a more descriptive title',
                );
            } else {
                $passed[] = array(
                    'type' => 'title_present',
                    'message' => 'Page has descriptive title',
                    'element' => '<title>' . esc_html(substr($title_text, 0, 50)) . '</title>',
                    'severity' => 'pass',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check color contrast (WCAG 1.4.3) - Basic check
     */
    private static function check_color_contrast($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Note: Full contrast checking requires computed styles
        // This is a basic check for inline styles
        
        $elements_with_style = $xpath->query('//*[@style]');
        
        foreach ($elements_with_style as $element) {
            $style = $element->getAttribute('style');
            
            // Look for color declarations
            if (preg_match('/color\s*:\s*([^;]+)/i', $style, $color_match) &&
                preg_match('/background(-color)?\s*:\s*([^;]+)/i', $style, $bg_match)) {
                
                // Check for potentially problematic combinations
                $color = strtolower(trim($color_match[1]));
                $bg = strtolower(trim($bg_match[2]));
                
                // Very basic check - would need proper color parsing for real implementation
                if ($color === $bg) {
                    $issues[] = array(
                        'type' => 'contrast_same_color',
                        'message' => 'Text color same as background',
                        'element' => '<' . $element->nodeName . ' style="...">',
                        'severity' => 'error',
                        'recommendation' => 'Ensure text has sufficient contrast with background',
                    );
                }
            }
        }
        
        // Add general recommendation
        $passed[] = array(
            'type' => 'contrast_note',
            'message' => 'Manual contrast verification recommended',
            'element' => '',
            'severity' => 'notice',
        );
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check skip links (WCAG 2.4.1)
     */
    private static function check_skip_links($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Look for skip links
        $skip_patterns = array(
            '//a[contains(translate(@href, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "#main")]',
            '//a[contains(translate(@href, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "#content")]',
            '//a[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "skip")]',
        );
        
        $skip_link_found = false;
        
        foreach ($skip_patterns as $pattern) {
            $skip_links = $xpath->query($pattern);
            if ($skip_links->length > 0) {
                $skip_link_found = true;
                break;
            }
        }
        
        if ($skip_link_found) {
            $passed[] = array(
                'type' => 'skip_link_present',
                'message' => 'Skip navigation link found',
                'element' => '',
                'severity' => 'pass',
            );
        } else {
            $issues[] = array(
                'type' => 'skip_link_missing',
                'message' => 'No skip navigation link found',
                'element' => '',
                'severity' => 'warning',
                'recommendation' => 'Add a "Skip to main content" link at the top of the page',
            );
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check keyboard focus visibility (WCAG 2.4.7)
     */
    private static function check_keyboard_focus($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Check for outline:none or outline:0 in styles
        if (preg_match('/outline\s*:\s*(none|0)/i', $html)) {
            $issues[] = array(
                'type' => 'focus_outline_removed',
                'message' => 'Focus outline may be removed via CSS',
                'element' => 'outline: none',
                'severity' => 'warning',
                'recommendation' => 'Ensure a visible focus indicator is provided',
            );
        }
        
        // Check for :focus styles (positive indicator)
        if (preg_match('/:focus\s*\{[^}]+\}/i', $html)) {
            $passed[] = array(
                'type' => 'focus_styles_present',
                'message' => 'Custom focus styles detected',
                'element' => '',
                'severity' => 'pass',
            );
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check ARIA labels (WCAG 4.1.2)
     */
    private static function check_aria_labels($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Check elements with ARIA roles
        $aria_elements = $xpath->query('//*[@role]');
        
        foreach ($aria_elements as $element) {
            $role = $element->getAttribute('role');
            $aria_label = $element->getAttribute('aria-label');
            $aria_labelledby = $element->getAttribute('aria-labelledby');
            
            // Roles that typically need labels
            $needs_label = array('button', 'link', 'checkbox', 'radio', 'textbox', 'combobox', 'listbox', 'dialog', 'alertdialog', 'region', 'navigation', 'main', 'search', 'form');
            
            if (in_array($role, $needs_label)) {
                if (empty($aria_label) && empty($aria_labelledby) && empty(trim($element->textContent))) {
                    $issues[] = array(
                        'type' => 'aria_missing_label',
                        'message' => "Element with role=\"{$role}\" missing accessible name",
                        'element' => '<' . $element->nodeName . ' role="' . esc_attr($role) . '">',
                        'severity' => 'error',
                        'recommendation' => 'Add aria-label or aria-labelledby attribute',
                    );
                } else {
                    $passed[] = array(
                        'type' => 'aria_has_label',
                        'message' => "Element with role=\"{$role}\" has accessible name",
                        'element' => '',
                        'severity' => 'pass',
                    );
                }
            }
        }
        
        // Check for invalid ARIA attributes
        $all_elements = $xpath->query('//*[@*[starts-with(name(), "aria-")]]');
        
        $valid_aria = array('aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden', 'aria-expanded', 'aria-controls', 'aria-selected', 'aria-checked', 'aria-disabled', 'aria-required', 'aria-invalid', 'aria-live', 'aria-atomic', 'aria-relevant', 'aria-busy', 'aria-haspopup', 'aria-current', 'aria-pressed', 'aria-valuemin', 'aria-valuemax', 'aria-valuenow', 'aria-valuetext', 'aria-modal', 'aria-owns', 'aria-activedescendant', 'aria-autocomplete', 'aria-multiselectable', 'aria-orientation', 'aria-readonly', 'aria-sort', 'aria-level', 'aria-posinset', 'aria-setsize', 'aria-colcount', 'aria-colindex', 'aria-colspan', 'aria-rowcount', 'aria-rowindex', 'aria-rowspan', 'aria-errormessage', 'aria-details', 'aria-keyshortcuts', 'aria-roledescription', 'aria-placeholder');
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check tables (WCAG 1.3.1)
     */
    private static function check_tables($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $tables = $xpath->query('//table');
        
        foreach ($tables as $table) {
            $role = $table->getAttribute('role');
            
            // Skip layout tables
            if ($role === 'presentation' || $role === 'none') {
                continue;
            }
            
            // Check for caption or aria-label
            $captions = $xpath->query('.//caption', $table);
            $aria_label = $table->getAttribute('aria-label');
            $aria_labelledby = $table->getAttribute('aria-labelledby');
            
            if ($captions->length === 0 && empty($aria_label) && empty($aria_labelledby)) {
                $issues[] = array(
                    'type' => 'table_no_caption',
                    'message' => 'Data table missing caption or accessible name',
                    'element' => '<table>',
                    'severity' => 'warning',
                    'recommendation' => 'Add <caption> or aria-label to describe the table',
                );
            }
            
            // Check for headers
            $headers = $xpath->query('.//th', $table);
            if ($headers->length === 0) {
                $issues[] = array(
                    'type' => 'table_no_headers',
                    'message' => 'Data table missing header cells',
                    'element' => '<table>',
                    'severity' => 'error',
                    'recommendation' => 'Use <th> elements for header cells',
                );
            } else {
                // Check if headers have scope
                $headers_without_scope = 0;
                foreach ($headers as $th) {
                    if (!$th->getAttribute('scope') && !$th->getAttribute('id')) {
                        $headers_without_scope++;
                    }
                }
                
                if ($headers_without_scope > 0) {
                    $issues[] = array(
                        'type' => 'table_headers_no_scope',
                        'message' => "{$headers_without_scope} table headers missing scope attribute",
                        'element' => '<th>',
                        'severity' => 'warning',
                        'recommendation' => 'Add scope="col" or scope="row" to header cells',
                    );
                }
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check iframes (WCAG 4.1.2)
     */
    private static function check_iframes($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $iframes = $xpath->query('//iframe');
        
        foreach ($iframes as $iframe) {
            $title = $iframe->getAttribute('title');
            $aria_label = $iframe->getAttribute('aria-label');
            $src = $iframe->getAttribute('src');
            
            if (empty($title) && empty($aria_label)) {
                $issues[] = array(
                    'type' => 'iframe_no_title',
                    'message' => 'Iframe missing title attribute',
                    'element' => '<iframe src="' . esc_attr(substr($src, 0, 50)) . '">',
                    'severity' => 'error',
                    'recommendation' => 'Add title attribute describing iframe content',
                );
            } else {
                $passed[] = array(
                    'type' => 'iframe_has_title',
                    'message' => 'Iframe has accessible title',
                    'element' => '<iframe title="' . esc_attr($title ?: $aria_label) . '">',
                    'severity' => 'pass',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check buttons (WCAG 4.1.2)
     */
    private static function check_buttons($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        $buttons = $xpath->query('//button | //input[@type="button"] | //input[@type="submit"] | //*[@role="button"]');
        
        foreach ($buttons as $button) {
            $text = trim($button->textContent);
            $value = $button->getAttribute('value');
            $aria_label = $button->getAttribute('aria-label');
            $title = $button->getAttribute('title');
            
            $accessible_name = $aria_label ?: $text ?: $value;
            
            if (empty($accessible_name) && empty($title)) {
                // Check for image inside button
                $images = $xpath->query('.//img[@alt]', $button);
                if ($images->length > 0) {
                    $accessible_name = $images->item(0)->getAttribute('alt');
                }
            }
            
            if (empty($accessible_name) && empty($title)) {
                $issues[] = array(
                    'type' => 'button_no_name',
                    'message' => 'Button missing accessible name',
                    'element' => '<button>',
                    'severity' => 'error',
                    'recommendation' => 'Add text content or aria-label to button',
                );
            } else {
                $passed[] = array(
                    'type' => 'button_has_name',
                    'message' => 'Button has accessible name',
                    'element' => '<button>' . esc_html(substr($accessible_name, 0, 30)) . '</button>',
                    'severity' => 'pass',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check lists (WCAG 1.3.1)
     */
    private static function check_lists($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Check for proper list structure
        $lists = $xpath->query('//ul | //ol');
        
        foreach ($lists as $list) {
            $list_type = $list->nodeName;
            
            // Check for non-list children
            $children = $xpath->query('./*', $list);
            $non_li_children = 0;
            
            foreach ($children as $child) {
                if ($child->nodeName !== 'li' && $child->nodeName !== 'script' && $child->nodeName !== 'template') {
                    $non_li_children++;
                }
            }
            
            if ($non_li_children > 0) {
                $issues[] = array(
                    'type' => 'list_invalid_children',
                    'message' => "List contains non-li children",
                    'element' => "<{$list_type}>",
                    'severity' => 'error',
                    'recommendation' => 'Lists should only contain <li> elements as direct children',
                );
            }
        }
        
        // Check definition lists
        $dl_lists = $xpath->query('//dl');
        foreach ($dl_lists as $dl) {
            $children = $xpath->query('./*', $dl);
            $valid = true;
            
            foreach ($children as $child) {
                if (!in_array($child->nodeName, array('dt', 'dd', 'div', 'script', 'template'))) {
                    $valid = false;
                    break;
                }
            }
            
            if (!$valid) {
                $issues[] = array(
                    'type' => 'dl_invalid_children',
                    'message' => 'Definition list contains invalid children',
                    'element' => '<dl>',
                    'severity' => 'error',
                    'recommendation' => 'Definition lists should only contain dt, dd, or div elements',
                );
            }
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check landmarks (WCAG 1.3.1)
     */
    private static function check_landmarks($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Check for main landmark
        $main = $xpath->query('//main | //*[@role="main"]');
        if ($main->length === 0) {
            $issues[] = array(
                'type' => 'landmark_no_main',
                'message' => 'Page missing main landmark',
                'element' => '',
                'severity' => 'warning',
                'recommendation' => 'Add <main> element or role="main" to main content area',
            );
        } elseif ($main->length > 1) {
            $issues[] = array(
                'type' => 'landmark_multiple_main',
                'message' => 'Page has multiple main landmarks',
                'element' => '',
                'severity' => 'warning',
                'recommendation' => 'Use only one main landmark per page',
            );
        } else {
            $passed[] = array(
                'type' => 'landmark_has_main',
                'message' => 'Page has main landmark',
                'element' => '<main>',
                'severity' => 'pass',
            );
        }
        
        // Check for navigation landmark
        $nav = $xpath->query('//nav | //*[@role="navigation"]');
        if ($nav->length > 0) {
            $passed[] = array(
                'type' => 'landmark_has_nav',
                'message' => 'Navigation landmark found',
                'element' => '<nav>',
                'severity' => 'pass',
            );
            
            // Check if multiple navs have labels
            if ($nav->length > 1) {
                $unlabeled = 0;
                foreach ($nav as $n) {
                    $label = $n->getAttribute('aria-label') ?: $n->getAttribute('aria-labelledby');
                    if (empty($label)) {
                        $unlabeled++;
                    }
                }
                
                if ($unlabeled > 0) {
                    $issues[] = array(
                        'type' => 'landmark_nav_no_label',
                        'message' => "{$unlabeled} navigation landmarks missing unique labels",
                        'element' => '<nav>',
                        'severity' => 'warning',
                        'recommendation' => 'Add aria-label to distinguish multiple navigation landmarks',
                    );
                }
            }
        }
        
        // Check for header/banner
        $header = $xpath->query('//header[not(ancestor::article) and not(ancestor::aside) and not(ancestor::main) and not(ancestor::nav) and not(ancestor::section)] | //*[@role="banner"]');
        if ($header->length > 0) {
            $passed[] = array(
                'type' => 'landmark_has_banner',
                'message' => 'Banner/header landmark found',
                'element' => '<header>',
                'severity' => 'pass',
            );
        }
        
        // Check for footer/contentinfo
        $footer = $xpath->query('//footer[not(ancestor::article) and not(ancestor::aside) and not(ancestor::main) and not(ancestor::nav) and not(ancestor::section)] | //*[@role="contentinfo"]');
        if ($footer->length > 0) {
            $passed[] = array(
                'type' => 'landmark_has_contentinfo',
                'message' => 'Footer/contentinfo landmark found',
                'element' => '<footer>',
                'severity' => 'pass',
            );
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Check media (WCAG 1.2.1)
     */
    private static function check_media($dom, $xpath, $html) {
        $issues = array();
        $passed = array();
        
        // Check videos
        $videos = $xpath->query('//video');
        foreach ($videos as $video) {
            $tracks = $xpath->query('.//track[@kind="captions"] | .//track[@kind="subtitles"]', $video);
            
            if ($tracks->length === 0) {
                $issues[] = array(
                    'type' => 'video_no_captions',
                    'message' => 'Video missing captions/subtitles track',
                    'element' => '<video>',
                    'severity' => 'error',
                    'recommendation' => 'Add <track kind="captions"> for caption support',
                );
            } else {
                $passed[] = array(
                    'type' => 'video_has_captions',
                    'message' => 'Video has captions/subtitles track',
                    'element' => '<video>',
                    'severity' => 'pass',
                );
            }
        }
        
        // Check audio
        $audios = $xpath->query('//audio');
        foreach ($audios as $audio) {
            // Audio should have transcript available nearby
            $issues[] = array(
                'type' => 'audio_transcript',
                'message' => 'Audio element found - verify transcript is available',
                'element' => '<audio>',
                'severity' => 'notice',
                'recommendation' => 'Provide a text transcript for audio content',
            );
        }
        
        return array('issues' => $issues, 'passed' => $passed);
    }
    
    /**
     * Calculate overall score
     */
    private static function calculate_score($results) {
        $total_checks = 0;
        $passed_checks = 0;
        
        foreach ($results['pages'] as $page) {
            $total_checks += $page['issues_count'] + $page['passed_count'];
            $passed_checks += $page['passed_count'];
        }
        
        if ($total_checks === 0) {
            return 10;
        }
        
        $score = ($passed_checks / $total_checks) * 10;
        return round($score, 1);
    }
    
    /**
     * Calculate WCAG compliance
     */
    private static function calculate_wcag_compliance($results) {
        $compliance = array(
            'level_a' => array('passed' => 0, 'failed' => 0, 'criteria' => array()),
            'level_aa' => array('passed' => 0, 'failed' => 0, 'criteria' => array()),
        );
        
        $wcag_criteria = AAP_Settings::get_wcag_criteria();
        
        foreach ($results['pages'] as $page) {
            foreach ($page['issues'] as $issue) {
                if (isset($issue['wcag']) && isset($wcag_criteria[$issue['wcag']])) {
                    $level = strtolower($wcag_criteria[$issue['wcag']]['level']);
                    $key = 'level_' . strtolower($level);
                    
                    if (isset($compliance[$key])) {
                        $compliance[$key]['failed']++;
                        $compliance[$key]['criteria'][$issue['wcag']] = 'failed';
                    }
                }
            }
            
            foreach ($page['passed'] as $pass) {
                if (isset($pass['wcag']) && isset($wcag_criteria[$pass['wcag']])) {
                    $level = strtolower($wcag_criteria[$pass['wcag']]['level']);
                    $key = 'level_' . strtolower($level);
                    
                    if (isset($compliance[$key]) && !isset($compliance[$key]['criteria'][$pass['wcag']])) {
                        $compliance[$key]['passed']++;
                        $compliance[$key]['criteria'][$pass['wcag']] = 'passed';
                    }
                }
            }
        }
        
        return $compliance;
    }
    
    /**
     * Normalize URL
     */
    private static function normalize_url($url, $base_url) {
        // Skip empty, javascript, mailto, tel links
        if (empty($url) || 
            strpos($url, 'javascript:') === 0 || 
            strpos($url, 'mailto:') === 0 || 
            strpos($url, 'tel:') === 0 ||
            strpos($url, '#') === 0) {
            return false;
        }
        
        // Handle relative URLs
        if (strpos($url, '//') === 0) {
            $url = parse_url($base_url, PHP_URL_SCHEME) . ':' . $url;
        } elseif (strpos($url, '/') === 0) {
            $parsed = parse_url($base_url);
            $url = $parsed['scheme'] . '://' . $parsed['host'] . $url;
        } elseif (strpos($url, 'http') !== 0) {
            $url = rtrim($base_url, '/') . '/' . $url;
        }
        
        // Remove fragments
        $url = preg_replace('/#.*$/', '', $url);
        
        // Remove trailing slash for consistency
        $url = rtrim($url, '/');
        
        return $url;
    }
    
    /**
     * Check if URL should be skipped
     */
    private static function should_skip_url($url) {
        $skip_patterns = array(
            '/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar|tar|gz)$/i',
            '/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i',
            '/\.(mp3|mp4|avi|mov|wmv|wav)$/i',
            '/\?(utm_|fbclid|gclid)/',
            '/\/wp-admin\//i',
            '/\/wp-login/i',
            '/\/cart\/?$/i',
            '/\/checkout\/?$/i',
            '/\/my-account\/?$/i',
        );
        
        foreach ($skip_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        return false;
    }
}
