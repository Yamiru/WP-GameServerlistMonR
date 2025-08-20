<?php
/**
 * Uninstall File for GameServerlistMonR
 * 
 * This file is executed when the plugin is deleted from WordPress
 * It removes all plugin data from the database
 * 
 * @package GameServerlistMonR
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Complete uninstall process
 */
class GSLM_Uninstall {
    
    /**
     * Run uninstall process
     */
    public static function run() {
        // Check user capabilities
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Start uninstall process
        self::log('Starting Game Server List Monitor uninstall process');
        
        // Remove all data
        self::removeDatabaseTables();
        self::removeOptions();
        self::removeTransients();
        self::removeUploadedFiles();
        self::removeCronJobs();
        self::cleanupCache();
        
        // Final cleanup
        self::finalCleanup();
        
        // Log completion
        self::log('Game Server List Monitor uninstall completed');
    }
    
    /**
     * Remove database tables
     */
    private static function removeDatabaseTables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'gslm_servers',
            $wpdb->prefix . 'gslm_cache',
            $wpdb->prefix . 'gslm_statistics'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        self::log('Database tables removed');
    }
    
    /**
     * Remove plugin options
     */
    private static function removeOptions() {
        $options = array(
            // Version options
            'gslm_version',
            'gslm_db_version',
            'gslm_activated',
            'gslm_activation_time',
            
            // Settings
            'gslm_default_refresh_interval',
            'gslm_default_auto_refresh',
            'gslm_default_theme',
            'gslm_enable_cache',
            'gslm_cache_duration',
            'gslm_enable_statistics',
            'gslm_themes',
            'gslm_supported_games',
            
            // Other
            'gslm_notices_dismissed',
            'gslm_first_install'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Remove any options that might have been created with prefix
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gslm_%'");
        
        self::log('Plugin options removed');
    }
    
    /**
     * Remove transients
     */
    private static function removeTransients() {
        global $wpdb;
        
        // Remove all plugin transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gslm_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_gslm_%'");
        
        // Remove site transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_gslm_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_gslm_%'");
        
        self::log('Transients removed');
    }
    
    /**
     * Remove uploaded files
     */
    private static function removeUploadedFiles() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/gslm';
        
        if (file_exists($plugin_upload_dir)) {
            self::deleteDirectory($plugin_upload_dir);
        }
        
        self::log('Uploaded files removed');
    }
    
    /**
     * Remove cron jobs
     */
    private static function removeCronJobs() {
        $cron_jobs = array(
            'gslm_cleanup_old_data',
            'gslm_refresh_cache',
            'gslm_update_statistics'
        );
        
        foreach ($cron_jobs as $job) {
            $timestamp = wp_next_scheduled($job);
            while ($timestamp) {
                wp_unschedule_event($timestamp, $job);
                $timestamp = wp_next_scheduled($job);
            }
        }
        
        self::log('Cron jobs removed');
    }
    
    /**
     * Cleanup cache
     */
    private static function cleanupCache() {
        // Clear object cache
        wp_cache_flush();
        
        // Clear page cache if caching plugins are installed
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        self::log('Cache cleared');
    }
    
    /**
     * Final cleanup
     */
    private static function finalCleanup() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any remaining options cache
        wp_cache_delete('alloptions', 'options');
        
        self::log('Final cleanup completed');
    }
    
    /**
     * Recursively delete directory
     */
    private static function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return @unlink($dir);
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        
        return @rmdir($dir);
    }
    
    /**
     * Log uninstall process
     */
    private static function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('[GSLM Uninstall] ' . $message);
        }
    }
}

// Run uninstall
GSLM_Uninstall::run();
