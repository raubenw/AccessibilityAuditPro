<?php
/**
 * PDF Report Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include TCPDF library
require_once AAP_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';

class AAP_PDF_Generator extends TCPDF {
    
    private $report_data;
    private $header_color;
    private $accent_color;
    private $company_name;
    private $company_logo;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $this->header_color = self::hex_to_rgb(AAP_Settings::get_option('report_header_color', '#07599c'));
        $this->accent_color = self::hex_to_rgb(AAP_Settings::get_option('report_accent_color', '#09e1c0'));
        $this->company_name = AAP_Settings::get_option('company_name', get_bloginfo('name'));
        $this->company_logo = AAP_Settings::get_option('company_logo', '');
        
        // Set document information
        $this->SetCreator($this->company_name);
        $this->SetAuthor($this->company_name);
        $this->SetTitle('Accessibility Audit Report');
        $this->SetSubject('WCAG 2.1 Compliance Report');
        
        // Set margins
        $this->SetMargins(15, 30, 15);
        $this->SetHeaderMargin(10);
        $this->SetFooterMargin(15);
        
        // Set auto page breaks
        $this->SetAutoPageBreak(true, 25);
        
        // Set font
        $this->SetFont('helvetica', '', 10);
    }
    
    /**
     * Custom header
     */
    public function Header() {
        // Logo
        if (!empty($this->company_logo) && file_exists($this->company_logo)) {
            $this->Image($this->company_logo, 15, 8, 30, '', '', '', 'T', false, 300, '', false, false, 0);
        }
        
        // Company name
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->SetXY(50, 10);
        $this->Cell(0, 10, $this->company_name, 0, 0, 'L');
        
        // Report title
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(128, 128, 128);
        $this->SetXY(50, 16);
        $this->Cell(0, 10, 'Accessibility Audit Report', 0, 0, 'L');
        
        // Separator line
        $this->SetDrawColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, 25, 195, 25);
    }
    
    /**
     * Custom footer
     */
    public function Footer() {
        $this->SetY(-20);
        
        // Separator line
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(128, 128, 128);
        
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
        
        // Powered by
        if (AAP_Settings::get_option('show_powered_by', true)) {
            $this->SetY(-15);
            $this->Cell(0, 10, 'Powered by Accessibility Audit Pro', 0, 0, 'R');
        }
    }
    
    /**
     * Generate PDF report
     */
    public static function generate($report_id) {
        $report = AAP_Database::get_report($report_id);
        
        if (!$report) {
            return false;
        }
        
        $report_data = maybe_unserialize($report['report_data']);
        $pages_data = AAP_Database::get_scanned_pages($report_id);
        
        $pdf = new self();
        $pdf->report_data = $report_data;
        
        // Cover page
        $pdf->AddPage();
        $pdf->generate_cover_page($report, $report_data);
        
        // Executive summary
        $pdf->AddPage();
        $pdf->generate_executive_summary($report_data);
        
        // WCAG Compliance summary
        $pdf->AddPage();
        $pdf->generate_wcag_summary($report_data);
        
        // Detailed results by page
        $pdf->AddPage();
        $pdf->generate_detailed_results($pages_data);
        
        // Device-specific screenshots
        $pdf->AddPage();
        $pdf->generate_screenshots_section($pages_data);
        
        // Recommendations
        $pdf->AddPage();
        $pdf->generate_recommendations($report_data);
        
        // Save PDF
        $upload_dir = wp_upload_dir();
        $reports_dir = $upload_dir['basedir'] . '/accessibility-audit-pro/reports';
        
        if (!file_exists($reports_dir)) {
            wp_mkdir_p($reports_dir);
        }
        
        $filename = 'accessibility-report-' . $report_id . '.pdf';
        $filepath = $reports_dir . '/' . $filename;
        
        $pdf->Output($filepath, 'F');
        
        // Update report with PDF path
        AAP_Database::update_report($report_id, array(
            'pdf_path' => $filepath,
        ));
        
        return $filepath;
    }
    
    /**
     * Generate cover page
     */
    private function generate_cover_page($report, $report_data) {
        $this->SetY(60);
        
        // Title
        $this->SetFont('helvetica', 'B', 28);
        $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->Cell(0, 15, 'Website Accessibility', 0, 1, 'C');
        $this->Cell(0, 15, 'Audit Report', 0, 1, 'C');
        
        // Website URL
        $this->Ln(10);
        $this->SetFont('helvetica', '', 14);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 10, $report['website_url'], 0, 1, 'C');
        
        // Date
        $this->Ln(5);
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, 'Generated: ' . date('F j, Y', strtotime($report['created_at'])), 0, 1, 'C');
        
        // Score circle
        $this->Ln(20);
        $score = $report_data['summary']['score'] ?? 0;
        $this->draw_score_circle($score);
        
        // Package info
        $this->SetY(200);
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 8, 'Package: ' . ucfirst(str_replace('_', ' ', $report['package_type'])), 0, 1, 'C');
        $this->Cell(0, 8, 'Pages Scanned: ' . $report_data['summary']['total_pages'], 0, 1, 'C');
        
        // Prepared for
        $this->Ln(10);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 8, 'Prepared for:', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 8, $report['customer_name'] ?: $report['customer_email'], 0, 1, 'C');
    }
    
    /**
     * Draw score circle
     */
    private function draw_score_circle($score) {
        $x = 105;
        $y = $this->GetY() + 25;
        $radius = 25;
        
        // Background circle
        $this->SetDrawColor(230, 230, 230);
        $this->SetLineWidth(3);
        $this->Circle($x, $y, $radius, 0, 360, 'D');
        
        // Score color based on value
        if ($score >= 8) {
            $color = array(34, 197, 94); // Green
        } elseif ($score >= 5) {
            $color = array(234, 179, 8); // Yellow
        } else {
            $color = array(239, 68, 68); // Red
        }
        
        $this->SetDrawColor($color[0], $color[1], $color[2]);
        $this->SetLineWidth(3);
        
        // Draw arc based on score
        $end_angle = ($score / 10) * 360;
        if ($end_angle > 0) {
            $this->Circle($x, $y, $radius, 270, 270 + $end_angle, 'D');
        }
        
        // Score text
        $this->SetFont('helvetica', 'B', 24);
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->SetXY($x - 15, $y - 8);
        $this->Cell(30, 10, number_format($score, 1), 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(128, 128, 128);
        $this->SetXY($x - 15, $y + 5);
        $this->Cell(30, 8, 'out of 10', 0, 1, 'C');
        
        // Label
        $this->SetXY($x - 30, $y + 30);
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->Cell(60, 10, 'Accessibility Score', 0, 1, 'C');
    }
    
    /**
     * Generate executive summary
     */
    private function generate_executive_summary($report_data) {
        $this->section_title('Executive Summary');
        
        $summary = $report_data['summary'];
        
        // Summary stats table
        $this->SetFont('helvetica', '', 11);
        $this->SetTextColor(60, 60, 60);
        
        $stats = array(
            array('Total Pages Scanned', $summary['total_pages']),
            array('Total Issues Found', $summary['total_issues']),
            array('Errors (Critical)', $summary['errors']),
            array('Warnings', $summary['warnings']),
            array('Passed Checks', $summary['passed']),
            array('Overall Score', $summary['score'] . '/10'),
        );
        
        $this->SetFillColor(245, 245, 245);
        $this->SetDrawColor(220, 220, 220);
        
        foreach ($stats as $i => $stat) {
            $fill = $i % 2 === 0;
            $this->SetFont('helvetica', '', 10);
            $this->Cell(90, 10, $stat[0], 1, 0, 'L', $fill);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(90, 10, $stat[1], 1, 1, 'R', $fill);
        }
        
        // Key findings
        $this->Ln(10);
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->Cell(0, 10, 'Key Findings', 0, 1);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(60, 60, 60);
        
        // Generate key findings based on issues
        $findings = $this->generate_key_findings($report_data);
        
        foreach ($findings as $finding) {
            $this->SetX(20);
            $this->MultiCell(170, 7, '• ' . $finding, 0, 'L');
        }
    }
    
    /**
     * Generate key findings from data
     */
    private function generate_key_findings($report_data) {
        $findings = array();
        $summary = $report_data['summary'];
        
        if ($summary['errors'] > 0) {
            $findings[] = sprintf('%d critical accessibility errors were found that require immediate attention.', $summary['errors']);
        } else {
            $findings[] = 'No critical accessibility errors were detected.';
        }
        
        if ($summary['warnings'] > 0) {
            $findings[] = sprintf('%d warnings were identified that should be reviewed and addressed.', $summary['warnings']);
        }
        
        if ($summary['score'] >= 8) {
            $findings[] = 'Overall, the website demonstrates good accessibility practices.';
        } elseif ($summary['score'] >= 5) {
            $findings[] = 'The website has moderate accessibility compliance but needs improvement.';
        } else {
            $findings[] = 'The website has significant accessibility issues that need to be addressed.';
        }
        
        return $findings;
    }
    
    /**
     * Generate WCAG compliance summary
     */
    private function generate_wcag_summary($report_data) {
        $this->section_title('WCAG 2.1 Compliance Summary');
        
        $wcag_criteria = AAP_Settings::get_wcag_criteria();
        $compliance = $report_data['wcag_compliance'] ?? array();
        
        // Level A compliance
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->Cell(0, 10, 'Level A Criteria', 0, 1);
        
        $this->render_compliance_table($wcag_criteria, 'A', $compliance);
        
        $this->Ln(5);
        
        // Level AA compliance
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->Cell(0, 10, 'Level AA Criteria', 0, 1);
        
        $this->render_compliance_table($wcag_criteria, 'AA', $compliance);
    }
    
    /**
     * Render compliance table
     */
    private function render_compliance_table($criteria, $level, $compliance) {
        $this->SetFont('helvetica', 'B', 9);
        $this->SetFillColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->SetTextColor(255, 255, 255);
        
        $this->Cell(25, 8, 'Criterion', 1, 0, 'C', true);
        $this->Cell(100, 8, 'Description', 1, 0, 'C', true);
        $this->Cell(55, 8, 'Status', 1, 1, 'C', true);
        
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(60, 60, 60);
        $this->SetFillColor(245, 245, 245);
        
        $i = 0;
        foreach ($criteria as $id => $criterion) {
            if ($criterion['level'] !== $level) {
                continue;
            }
            
            $fill = $i % 2 === 0;
            $status = $this->get_criterion_status($id, $compliance);
            
            $this->Cell(25, 7, $id, 1, 0, 'C', $fill);
            $this->Cell(100, 7, $criterion['name'], 1, 0, 'L', $fill);
            
            // Status with color
            if ($status === 'passed') {
                $this->SetTextColor(34, 197, 94);
                $status_text = '✓ Passed';
            } elseif ($status === 'failed') {
                $this->SetTextColor(239, 68, 68);
                $status_text = '✗ Failed';
            } else {
                $this->SetTextColor(156, 163, 175);
                $status_text = '- Not Tested';
            }
            
            $this->Cell(55, 7, $status_text, 1, 1, 'C', $fill);
            $this->SetTextColor(60, 60, 60);
            
            $i++;
        }
    }
    
    /**
     * Get criterion status
     */
    private function get_criterion_status($criterion_id, $compliance) {
        $level = 'level_' . strtolower(AAP_Settings::get_wcag_criteria()[$criterion_id]['level'] ?? 'a');
        
        if (isset($compliance[$level]['criteria'][$criterion_id])) {
            return $compliance[$level]['criteria'][$criterion_id];
        }
        
        return 'not_tested';
    }
    
    /**
     * Generate detailed results
     */
    private function generate_detailed_results($pages_data) {
        $this->section_title('Detailed Results by Page');
        
        $current_url = '';
        
        foreach ($pages_data as $page) {
            // New page header if URL changes
            if ($page['page_url'] !== $current_url) {
                if ($current_url !== '') {
                    $this->Ln(5);
                }
                
                $current_url = $page['page_url'];
                
                $this->SetFont('helvetica', 'B', 11);
                $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
                $this->MultiCell(0, 8, $page['page_title'] ?: $page['page_url'], 0, 'L');
                
                $this->SetFont('helvetica', '', 8);
                $this->SetTextColor(128, 128, 128);
                $this->Cell(0, 5, $page['page_url'], 0, 1);
                $this->Ln(3);
            }
            
            // Device type header
            $this->SetFont('helvetica', 'B', 9);
            $this->SetFillColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 7, ucfirst($page['device_type']) . ' View', 0, 1, 'L', true);
            
            // Stats row
            $this->SetFont('helvetica', '', 9);
            $this->SetTextColor(60, 60, 60);
            $this->Cell(45, 6, 'Errors: ' . $page['errors_count'], 1, 0, 'C');
            $this->Cell(45, 6, 'Warnings: ' . $page['warnings_count'], 1, 0, 'C');
            $this->Cell(45, 6, 'Passed: ' . $page['passed_count'], 1, 0, 'C');
            $this->Cell(45, 6, 'Total: ' . $page['issues_count'], 1, 1, 'C');
            
            // Issues list
            $scan_data = maybe_unserialize($page['scan_data']);
            
            if (!empty($scan_data['issues'])) {
                $this->Ln(2);
                $this->SetFont('helvetica', '', 8);
                
                foreach (array_slice($scan_data['issues'], 0, 10) as $issue) {
                    $this->SetX(20);
                    
                    // Severity indicator
                    if ($issue['severity'] === 'error') {
                        $this->SetTextColor(239, 68, 68);
                        $prefix = '✗ ';
                    } else {
                        $this->SetTextColor(234, 179, 8);
                        $prefix = '⚠ ';
                    }
                    
                    $this->Cell(5, 5, $prefix, 0, 0);
                    $this->SetTextColor(60, 60, 60);
                    $this->MultiCell(155, 5, $issue['message'], 0, 'L');
                }
                
                if (count($scan_data['issues']) > 10) {
                    $this->SetX(20);
                    $this->SetTextColor(128, 128, 128);
                    $this->Cell(0, 5, '... and ' . (count($scan_data['issues']) - 10) . ' more issues', 0, 1);
                }
            }
            
            $this->Ln(5);
            
            // Check if need new page
            if ($this->GetY() > 250) {
                $this->AddPage();
            }
        }
    }
    
    /**
     * Generate screenshots section
     */
    private function generate_screenshots_section($pages_data) {
        $this->section_title('Device Screenshots');
        
        $devices = array('desktop', 'tablet', 'mobile');
        $processed_urls = array();
        
        foreach ($pages_data as $page) {
            // Only show one set of screenshots per URL
            if (in_array($page['page_url'], $processed_urls)) {
                continue;
            }
            
            if ($page['device_type'] === 'desktop') {
                $processed_urls[] = $page['page_url'];
                
                // Page title
                $this->SetFont('helvetica', 'B', 10);
                $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
                $this->Cell(0, 8, $page['page_title'] ?: 'Homepage', 0, 1);
                
                // Find all device screenshots for this URL
                $url_pages = array_filter($pages_data, function($p) use ($page) {
                    return $p['page_url'] === $page['page_url'];
                });
                
                // Display screenshots side by side
                $x_positions = array(15, 75, 140);
                $widths = array(55, 55, 40);
                $labels = array('Desktop', 'Tablet', 'Mobile');
                
                $start_y = $this->GetY();
                
                $i = 0;
                foreach ($devices as $device) {
                    $device_page = array_filter($url_pages, function($p) use ($device) {
                        return $p['device_type'] === $device;
                    });
                    
                    if (!empty($device_page)) {
                        $device_page = reset($device_page);
                        
                        // Label
                        $this->SetXY($x_positions[$i], $start_y);
                        $this->SetFont('helvetica', '', 8);
                        $this->SetTextColor(128, 128, 128);
                        $this->Cell($widths[$i], 5, $labels[$i], 0, 0, 'C');
                        
                        // Screenshot
                        if (!empty($device_page['screenshot_path']) && file_exists($device_page['screenshot_path'])) {
                            $this->Image(
                                $device_page['screenshot_path'],
                                $x_positions[$i],
                                $start_y + 6,
                                $widths[$i],
                                0,
                                '',
                                '',
                                '',
                                false,
                                300,
                                '',
                                false,
                                false,
                                1
                            );
                        }
                    }
                    $i++;
                }
                
                $this->SetY($start_y + 60);
                $this->Ln(10);
                
                // Check for new page
                if ($this->GetY() > 200) {
                    $this->AddPage();
                }
            }
        }
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations($report_data) {
        $this->section_title('Recommendations');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(60, 60, 60);
        
        $recommendations = array(
            array(
                'priority' => 'High',
                'title' => 'Fix Critical Errors',
                'description' => 'Address all critical accessibility errors first, particularly missing alt text on images and form labels.',
            ),
            array(
                'priority' => 'High',
                'title' => 'Improve Keyboard Navigation',
                'description' => 'Ensure all interactive elements are keyboard accessible and have visible focus indicators.',
            ),
            array(
                'priority' => 'Medium',
                'title' => 'Review Color Contrast',
                'description' => 'Verify that all text has sufficient color contrast ratio (4.5:1 for normal text, 3:1 for large text).',
            ),
            array(
                'priority' => 'Medium',
                'title' => 'Add Skip Links',
                'description' => 'Implement "Skip to main content" links to help keyboard users navigate efficiently.',
            ),
            array(
                'priority' => 'Medium',
                'title' => 'Structure Headings Properly',
                'description' => 'Use a logical heading hierarchy (H1-H6) without skipping levels.',
            ),
            array(
                'priority' => 'Low',
                'title' => 'Enhance ARIA Usage',
                'description' => 'Add appropriate ARIA labels and roles to complex interactive components.',
            ),
            array(
                'priority' => 'Low',
                'title' => 'Test with Screen Readers',
                'description' => 'Conduct manual testing with screen readers like NVDA or VoiceOver.',
            ),
        );
        
        foreach ($recommendations as $rec) {
            // Priority badge
            if ($rec['priority'] === 'High') {
                $this->SetFillColor(254, 226, 226);
                $this->SetTextColor(185, 28, 28);
            } elseif ($rec['priority'] === 'Medium') {
                $this->SetFillColor(254, 249, 195);
                $this->SetTextColor(161, 98, 7);
            } else {
                $this->SetFillColor(220, 252, 231);
                $this->SetTextColor(22, 101, 52);
            }
            
            $this->SetFont('helvetica', 'B', 8);
            $this->Cell(20, 6, $rec['priority'], 0, 0, 'C', true);
            
            // Title
            $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, '  ' . $rec['title'], 0, 1);
            
            // Description
            $this->SetTextColor(60, 60, 60);
            $this->SetFont('helvetica', '', 9);
            $this->SetX(20);
            $this->MultiCell(170, 5, $rec['description'], 0, 'L');
            $this->Ln(3);
        }
        
        // Next steps
        $this->Ln(10);
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->Cell(0, 10, 'Next Steps', 0, 1);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(60, 60, 60);
        
        $next_steps = array(
            'Review this report and prioritize issues based on severity.',
            'Create a remediation plan with assigned tasks and deadlines.',
            'Implement fixes starting with critical errors.',
            'Re-scan your website after making changes to verify improvements.',
            'Consider ongoing accessibility monitoring for continued compliance.',
        );
        
        foreach ($next_steps as $i => $step) {
            $this->SetX(20);
            $this->Cell(8, 7, ($i + 1) . '.', 0, 0);
            $this->MultiCell(0, 7, $step, 0, 'L');
        }
        
        // Contact info
        $this->Ln(15);
        $this->SetFillColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 10, 'Need Help? Contact Us', 0, 1, 'C', true);
        
        $this->SetFont('helvetica', '', 10);
        $this->Ln(3);
        $company_email = AAP_Settings::get_option('company_email');
        $this->Cell(0, 7, 'Email: ' . $company_email, 0, 1, 'C');
        $this->Cell(0, 7, 'Website: ' . home_url(), 0, 1, 'C');
    }
    
    /**
     * Section title helper
     */
    private function section_title($title) {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor($this->header_color[0], $this->header_color[1], $this->header_color[2]);
        $this->Cell(0, 15, $title, 0, 1);
        
        $this->SetDrawColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
        $this->SetLineWidth(1);
        $this->Line(15, $this->GetY(), 60, $this->GetY());
        
        $this->Ln(8);
    }
    
    /**
     * Convert hex color to RGB
     */
    private static function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }
    
    /**
     * Get PDF download URL
     */
    public static function get_download_url($report_id) {
        return add_query_arg(array(
            'aap_download' => 'pdf',
            'report_id' => $report_id,
            'nonce' => wp_create_nonce('aap_download_pdf_' . $report_id),
        ), home_url());
    }
}
