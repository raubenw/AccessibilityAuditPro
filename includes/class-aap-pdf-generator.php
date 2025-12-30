<?php
/**
 * PDF Report Generator
 * Generates professional PDF accessibility reports
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_PDF_Generator {
    
    /**
     * Generate PDF report
     */
    public static function generate($report_id) {
        // Load TCPDF library
        $tcpdf_path = AAP_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
        if (!file_exists($tcpdf_path)) {
            error_log('Accessibility Audit Pro: TCPDF library not found at ' . $tcpdf_path);
            return false;
        }
        
        require_once $tcpdf_path;
        
        // Get report data
        $report = AAP_Database::get_report($report_id);
        if (!$report) {
            return false;
        }
        
        $report_data = maybe_unserialize($report['report_data']);
        $pages_data = AAP_Database::get_scanned_pages($report_id);
        
        // Create PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Get settings
        $header_color = self::hex_to_rgb(AAP_Settings::get_option('report_header_color', '#07599c'));
        $accent_color = self::hex_to_rgb(AAP_Settings::get_option('report_accent_color', '#09e1c0'));
        $company_name = AAP_Settings::get_option('company_name', get_bloginfo('name'));
        
        // Set document information
        $pdf->SetCreator($company_name);
        $pdf->SetAuthor($company_name);
        $pdf->SetTitle('Accessibility Audit Report');
        $pdf->SetSubject('WCAG 2.1 Compliance Report');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 25);
        
        // Cover page
        $pdf->AddPage();
        self::generate_cover_page($pdf, $report, $report_data, $header_color, $accent_color, $company_name);
        
        // Executive summary
        $pdf->AddPage();
        self::generate_executive_summary($pdf, $report_data, $header_color, $accent_color);
        
        // WCAG Compliance summary
        $pdf->AddPage();
        self::generate_wcag_summary($pdf, $report_data, $header_color, $accent_color);
        
        // Detailed results
        $pdf->AddPage();
        self::generate_detailed_results($pdf, $pages_data, $header_color, $accent_color);
        
        // Recommendations
        $pdf->AddPage();
        self::generate_recommendations($pdf, $report_data, $header_color, $accent_color);
        
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
    private static function generate_cover_page($pdf, $report, $report_data, $header_color, $accent_color, $company_name) {
        $pdf->SetY(40);
        
        // Company name
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
        $pdf->Cell(0, 10, $company_name, 0, 1, 'C');
        
        $pdf->SetY(70);
        
        // Title
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->Cell(0, 15, 'Website Accessibility', 0, 1, 'C');
        $pdf->Cell(0, 15, 'Audit Report', 0, 1, 'C');
        
        // Website URL
        $pdf->Ln(15);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 10, $report['website_url'], 0, 1, 'C');
        
        // Date
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Generated: ' . date('F j, Y', strtotime($report['created_at'])), 0, 1, 'C');
        
        // Score
        $pdf->Ln(20);
        $score = isset($report_data['summary']['score']) ? $report_data['summary']['score'] : 0;
        
        if ($score >= 8) {
            $score_color = array(34, 197, 94);
        } elseif ($score >= 5) {
            $score_color = array(234, 179, 8);
        } else {
            $score_color = array(239, 68, 68);
        }
        
        $pdf->SetFont('helvetica', 'B', 48);
        $pdf->SetTextColor($score_color[0], $score_color[1], $score_color[2]);
        $pdf->Cell(0, 20, number_format($score, 1) . '/10', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 8, 'Accessibility Score', 0, 1, 'C');
        
        // Package info
        $pdf->SetY(200);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Package: ' . ucfirst(str_replace('_', ' ', $report['package_type'])), 0, 1, 'C');
        $total_pages = isset($report_data['summary']['total_pages']) ? $report_data['summary']['total_pages'] : 0;
        $pdf->Cell(0, 8, 'Pages Scanned: ' . $total_pages, 0, 1, 'C');
        
        // Prepared for
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 8, 'Prepared for:', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, $report['customer_name'] ?: $report['customer_email'], 0, 1, 'C');
    }
    
    /**
     * Generate executive summary
     */
    private static function generate_executive_summary($pdf, $report_data, $header_color, $accent_color) {
        self::section_title($pdf, 'Executive Summary', $header_color, $accent_color);
        
        $summary = isset($report_data['summary']) ? $report_data['summary'] : array(
            'total_pages' => 0,
            'total_issues' => 0,
            'errors' => 0,
            'warnings' => 0,
            'passed' => 0,
            'score' => 0,
        );
        
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor(60, 60, 60);
        
        $stats = array(
            array('Total Pages Scanned', $summary['total_pages']),
            array('Total Issues Found', $summary['total_issues']),
            array('Errors (Critical)', $summary['errors']),
            array('Warnings', $summary['warnings']),
            array('Passed Checks', $summary['passed']),
            array('Overall Score', $summary['score'] . '/10'),
        );
        
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetDrawColor(220, 220, 220);
        
        foreach ($stats as $i => $stat) {
            $fill = $i % 2 === 0;
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(90, 10, $stat[0], 1, 0, 'L', $fill);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(90, 10, $stat[1], 1, 1, 'R', $fill);
        }
        
        // Key findings
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
        $pdf->Cell(0, 10, 'Key Findings', 0, 1);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        
        $findings = array();
        
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
        
        foreach ($findings as $finding) {
            $pdf->SetX(20);
            $pdf->MultiCell(170, 7, '• ' . $finding, 0, 'L');
        }
    }
    
    /**
     * Generate WCAG compliance summary
     */
    private static function generate_wcag_summary($pdf, $report_data, $header_color, $accent_color) {
        self::section_title($pdf, 'WCAG 2.1 Compliance Summary', $header_color, $accent_color);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        
        $pdf->MultiCell(0, 7, 'This report evaluates your website against WCAG 2.1 Level A and AA success criteria. Below is a summary of the guidelines checked:', 0, 'L');
        
        $pdf->Ln(5);
        
        // Simplified compliance info
        $guidelines = array(
            array('1.1 Text Alternatives', 'Provide text alternatives for non-text content'),
            array('1.3 Adaptable', 'Create content that can be presented in different ways'),
            array('1.4 Distinguishable', 'Make it easier for users to see and hear content'),
            array('2.1 Keyboard Accessible', 'Make all functionality available from keyboard'),
            array('2.4 Navigable', 'Provide ways to help users navigate and find content'),
            array('3.1 Readable', 'Make text content readable and understandable'),
            array('3.3 Input Assistance', 'Help users avoid and correct mistakes'),
            array('4.1 Compatible', 'Maximize compatibility with assistive technologies'),
        );
        
        $pdf->SetFillColor($header_color[0], $header_color[1], $header_color[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(50, 8, 'Guideline', 1, 0, 'C', true);
        $pdf->Cell(130, 8, 'Description', 1, 1, 'C', true);
        
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFillColor(245, 245, 245);
        
        foreach ($guidelines as $i => $guideline) {
            $fill = $i % 2 === 0;
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(50, 8, $guideline[0], 1, 0, 'L', $fill);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(130, 8, $guideline[1], 1, 1, 'L', $fill);
        }
    }
    
    /**
     * Generate detailed results
     */
    private static function generate_detailed_results($pdf, $pages_data, $header_color, $accent_color) {
        self::section_title($pdf, 'Detailed Results by Page', $header_color, $accent_color);
        
        if (empty($pages_data)) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 10, 'No page data available.', 0, 1);
            return;
        }
        
        $current_url = '';
        
        foreach ($pages_data as $page) {
            // New page header if URL changes
            if ($page['page_url'] !== $current_url) {
                if ($current_url !== '') {
                    $pdf->Ln(5);
                }
                
                $current_url = $page['page_url'];
                
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
                $pdf->MultiCell(0, 8, $page['page_title'] ?: $page['page_url'], 0, 'L');
                
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(128, 128, 128);
                $pdf->Cell(0, 5, $page['page_url'], 0, 1);
                $pdf->Ln(3);
            }
            
            // Device type header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor($accent_color[0], $accent_color[1], $accent_color[2]);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 7, ucfirst($page['device_type']) . ' View', 0, 1, 'L', true);
            
            // Stats row
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->Cell(45, 6, 'Errors: ' . $page['errors_count'], 1, 0, 'C');
            $pdf->Cell(45, 6, 'Warnings: ' . $page['warnings_count'], 1, 0, 'C');
            $pdf->Cell(45, 6, 'Passed: ' . $page['passed_count'], 1, 0, 'C');
            $pdf->Cell(45, 6, 'Total: ' . $page['issues_count'], 1, 1, 'C');
            
            $pdf->Ln(5);
            
            // Check if need new page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
            }
        }
    }
    
    /**
     * Generate recommendations
     */
    private static function generate_recommendations($pdf, $report_data, $header_color, $accent_color) {
        self::section_title($pdf, 'Recommendations', $header_color, $accent_color);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        
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
                'title' => 'Structure Headings Properly',
                'description' => 'Use a logical heading hierarchy (H1-H6) without skipping levels.',
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
                $pdf->SetFillColor(254, 226, 226);
                $pdf->SetTextColor(185, 28, 28);
            } elseif ($rec['priority'] === 'Medium') {
                $pdf->SetFillColor(254, 249, 195);
                $pdf->SetTextColor(161, 98, 7);
            } else {
                $pdf->SetFillColor(220, 252, 231);
                $pdf->SetTextColor(22, 101, 52);
            }
            
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(20, 6, $rec['priority'], 0, 0, 'C', true);
            
            // Title
            $pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, '  ' . $rec['title'], 0, 1);
            
            // Description
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(20);
            $pdf->MultiCell(170, 5, $rec['description'], 0, 'L');
            $pdf->Ln(3);
        }
        
        // Contact info
        $pdf->Ln(15);
        $pdf->SetFillColor($accent_color[0], $accent_color[1], $accent_color[2]);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'Need Help? Contact Us', 0, 1, 'C', true);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(3);
        $company_email = AAP_Settings::get_option('company_email', get_option('admin_email'));
        $pdf->Cell(0, 7, 'Email: ' . $company_email, 0, 1, 'C');
        $pdf->Cell(0, 7, 'Website: ' . home_url(), 0, 1, 'C');
    }
    
    /**
     * Section title helper
     */
    private static function section_title($pdf, $title, $header_color, $accent_color) {
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
        $pdf->Cell(0, 15, $title, 0, 1);
        
        $pdf->SetDrawColor($accent_color[0], $accent_color[1], $accent_color[2]);
        $pdf->SetLineWidth(1);
        $pdf->Line(15, $pdf->GetY(), 60, $pdf->GetY());
        
        $pdf->Ln(8);
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
