<?php
/**
 * Plugin Deactivator
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAP_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('aap_cleanup_temp_files');
        wp_clear_scheduled_hook('aap_process_pending_scans');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
