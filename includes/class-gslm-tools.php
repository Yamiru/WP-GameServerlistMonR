<?php
/**
 * Tools & Maintenance Class for GameServerlistMonR
 * 
 * @package GameServerlistMonR
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_Tools {
    
    /**
     * Reinstall plugin (reset all data)
     */
    public static function reinstallPlugin() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => 'Insufficient permissions');
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Step 1: Backup current settings
            $backup_settings = array(
                'refresh_interval' => get_option('gslm_default_refresh_interval'),
                'auto_refresh' => get_option('gslm_default_auto_refresh'),
                'theme' => get_option('gslm_default_theme'),
                'cache' => get_option('gslm_enable_cache'),
                'cache_duration' => get_option('gslm_cache_duration')
            );
            
            // Step 2: Drop all tables
            GSLM_Database::dropTables();
            
            // Step 3: Remove all options
            GSLM_Database::removeOptions();
            
            // Step 4: Clear all transients
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gslm_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_gslm_%'");
            
            // Step 5: Recreate tables
            GSLM_Database::createTables();
            
            // Step 6: Restore default options
            GSLM_Database::setDefaultOptions();
            
            // Step 7: Restore backed up settings (optional)
            if (!empty($backup_settings['refresh_interval'])) {
                update_option('gslm_default_refresh_interval', $backup_settings['refresh_interval']);
                update_option('gslm_default_auto_refresh', $backup_settings['auto_refresh']);
                update_option('gslm_default_theme', $backup_settings['theme']);
                update_option('gslm_enable_cache', $backup_settings['cache']);
                update_option('gslm_cache_duration', $backup_settings['cache_duration']);
            }
            
            // Step 8: Clear cache
            wp_cache_flush();
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return array(
                'success' => true, 
                'message' => 'Plugin has been successfully reinstalled. All server data has been removed.'
            );
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            return array(
                'success' => false,
                'message' => 'Reinstall failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Export servers to JSON
     */
    public static function exportServers() {
        $servers = GSLM_Database::getServers();
        
        $export_data = array(
            'version' => GSLM_VERSION,
            'export_date' => current_time('mysql'),
            'servers' => array()
        );
        
        foreach ($servers as $server) {
            $export_data['servers'][] = array(
                'server_name' => $server->server_name,
                'server_type' => $server->server_type,
                'server_ip' => $server->server_ip,
                'server_port' => $server->server_port,
                'query_port' => $server->query_port,
                'game_icon' => $server->game_icon,
                'custom_icon_url' => $server->custom_icon_url,
                'refresh_interval' => $server->refresh_interval,
                'auto_refresh' => $server->auto_refresh,
                'theme_style' => $server->theme_style,
                'show_players' => $server->show_players,
                'show_map' => $server->show_map,
                'show_connect' => $server->show_connect,
                'custom_connect_link' => $server->custom_connect_link,
                'discord_invite' => $server->discord_invite
            );
        }
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import servers from JSON
     */
    public static function importServers($json_data) {
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['servers'])) {
            return array('success' => false, 'message' => 'Invalid import data');
        }
        
        $imported = 0;
        $failed = 0;
        
        foreach ($data['servers'] as $server_data) {
            $result = GSLM_Database::saveServer($server_data);
            if ($result) {
                $imported++;
            } else {
                $failed++;
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf('Import complete: %d servers imported, %d failed', $imported, $failed),
            'imported' => $imported,
            'failed' => $failed
        );
    }
    
    /**
     * Clean up old statistics
     */
    public static function cleanupStatistics($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gslm_statistics';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        return array(
            'success' => true,
            'message' => sprintf('Deleted %d old statistics records', $deleted),
            'deleted' => $deleted
        );
    }
    
    /**
     * Optimize database tables
     */
    public static function optimizeTables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'gslm_servers',
            $wpdb->prefix . 'gslm_cache',
            $wpdb->prefix . 'gslm_statistics'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
        
        return array(
            'success' => true,
            'message' => 'Database tables optimized successfully'
        );
    }
    
    /**
     * Get database size info
     */
    public static function getDatabaseInfo() {
        global $wpdb;
        
        $info = array();
        
        $tables = array(
            'servers' => $wpdb->prefix . 'gslm_servers',
            'cache' => $wpdb->prefix . 'gslm_cache',
            'statistics' => $wpdb->prefix . 'gslm_statistics'
        );
        
        foreach ($tables as $name => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            
            $size = $wpdb->get_row("
                SELECT 
                    ROUND(((data_length + index_length) / 1024), 2) AS size_kb
                FROM information_schema.TABLES 
                WHERE table_schema = '" . DB_NAME . "' 
                AND table_name = '$table'
            ");
            
            $info[$name] = array(
                'count' => $count,
                'size' => $size ? $size->size_kb . ' KB' : 'Unknown'
            );
        }
        
        return $info;
    }
}