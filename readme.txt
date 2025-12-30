=== Accessibility Audit Pro ===
Contributors: openwebaccess
Tags: accessibility, wcag, ada, audit, compliance, a11y
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional accessibility auditing service for WordPress. Let visitors pay to have their websites scanned for WCAG 2.1 compliance.

== Description ==

Accessibility Audit Pro is a comprehensive WordPress plugin that allows you to offer professional accessibility auditing services directly from your website. Users can pay via PayPal to have their websites thoroughly scanned for WCAG 2.1 Level A and AA compliance issues.

**Key Features:**

* **WCAG 2.1 Compliance Testing** - Full Level A and AA criteria checking
* **Multi-Page Scanning** - Packages from 5 to 100+ pages
* **Multi-Device Testing** - Desktop, tablet, and mobile viewports
* **Screenshot Capture** - Visual documentation across all devices
* **Professional PDF Reports** - Branded, downloadable reports
* **PayPal Integration** - Secure payment processing
* **Email Notifications** - Order confirmation and report delivery
* **Admin Free Access** - Run unlimited free scans for client prospecting
* **REST API** - Integrate with external services

**Pricing Packages (Configurable):**

* 5 Pages - $29
* 10 Pages - $49
* 25 Pages - $99
* 50 Pages - $179
* 100 Pages - $299

**WCAG Checks Include:**

* Image alt text verification
* Form label associations
* Heading hierarchy structure
* Link text descriptiveness
* Color contrast ratios
* Keyboard accessibility
* ARIA attributes usage
* Skip navigation links
* Table accessibility
* Video/audio captions
* Language attributes
* And much more...

== Installation ==

1. Upload the `accessibility-audit-pro` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Accessibility Audit > Settings to configure:
   - Company information
   - PayPal API credentials
   - Pricing packages
   - Report branding
4. Create a page and add the `[accessibility_audit]` shortcode
5. Test the payment flow in sandbox mode before going live

== Shortcodes ==

**Main Audit Form:**
`[accessibility_audit show_pricing="true" show_preview="true" default_package="10_pages"]`

**Pricing Table Only:**
`[accessibility_audit_pricing selectable="true" default="10_pages"]`

**Free Preview Scanner:**
`[accessibility_audit_preview show_cta="true"]`

**Report Status:**
`[accessibility_audit_status report_id="123"]`

== Configuration ==

**PayPal Setup:**

1. Create a PayPal Developer account at developer.paypal.com
2. Create a new app to get Client ID and Secret
3. Add credentials to Settings > PayPal Settings
4. Enable Sandbox mode for testing
5. Switch to Live mode when ready for production

**Screenshot Providers:**

The plugin supports multiple screenshot providers:

* **Internal (Free)** - Uses Google PageSpeed API
* **ScreenshotMachine** - screenshotmachine.com
* **Screenshotlayer** - screenshotlayer.com  
* **ApiFlash** - apiflash.com

== REST API ==

The plugin provides a REST API for integration:

* `GET /wp-json/accessibility-audit/v1/packages` - List pricing packages
* `POST /wp-json/accessibility-audit/v1/preview` - Quick scan preview
* `GET /wp-json/accessibility-audit/v1/reports/{id}` - Get report details
* `GET /wp-json/accessibility-audit/v1/reports/{id}/status` - Check status

Admin-only endpoints require authentication.

== Frequently Asked Questions ==

= Can administrators run free scans? =

Yes! By default, site administrators can run unlimited free scans. This is perfect for prospecting potential clients. You can disable this in Settings.

= How long do scans take? =

Most scans complete within 5-15 minutes depending on the number of pages. Large sites (100+ pages) may take up to 30 minutes.

= What WCAG criteria are tested? =

The plugin tests for WCAG 2.1 Level A and Level AA success criteria including images, forms, headings, links, color contrast, keyboard accessibility, ARIA usage, and more.

= Can I customize the reports? =

Yes! You can set your company name, logo, and brand colors in the settings. Reports will be generated with your branding.

= Does this work with any website? =

Yes, you can audit any publicly accessible website. Sites behind logins or firewalls may not be fully scannable.

== Screenshots ==

1. Frontend audit form with pricing
2. Admin dashboard with statistics
3. Report view with accessibility score
4. PDF report example
5. Settings page

== Changelog ==

= 1.0.0 =
* Initial release
* Full WCAG 2.1 Level A & AA testing
* PayPal payment integration
* Multi-device screenshot capture
* PDF report generation
* Email notifications
* REST API
* Admin dashboard

== Upgrade Notice ==

= 1.0.0 =
Initial release of Accessibility Audit Pro.

== Credits ==

* TCPDF library for PDF generation
* Google PageSpeed API for screenshots
* PayPal REST API for payments

== Support ==

For support, please visit [Open Web Access](https://openwebaccess.com) or contact us at support@openwebaccess.com.
