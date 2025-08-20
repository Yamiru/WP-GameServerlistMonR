<?php
/**
 * Database Management Class for GameServerlistMonR
 * 
 * @package GameServerlistMonR
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_Database {
    
    /**
     * Get table name with prefix
     */
    private static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'gslm_' . $table;
    }
    
    /**
     * Check if column exists in table
     */
    private static function column_exists($table, $column) {
        global $wpdb;
        $table_name = self::get_table_name($table);
        $result = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$table_name` LIKE %s",
            $column
        ));
        return !empty($result);
    }
    
    /**
     * Create database tables
     */
    public static function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Servers table - WITHOUT is_active for backwards compatibility
        $table_servers = self::get_table_name('servers');
        $sql_servers = "CREATE TABLE $table_servers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            server_name varchar(255) DEFAULT NULL,
            server_type varchar(100) NOT NULL,
            server_ip varchar(255) NOT NULL,
            server_port int(11) NOT NULL DEFAULT 25565,
            query_port int(11) DEFAULT NULL,
            game_icon varchar(500) DEFAULT NULL,
            custom_icon_url varchar(500) DEFAULT NULL,
            refresh_interval int(11) NOT NULL DEFAULT 60,
            auto_refresh tinyint(1) DEFAULT 1,
            theme_style varchar(50) NOT NULL DEFAULT 'modern',
            show_players tinyint(1) DEFAULT 1,
            show_map tinyint(1) DEFAULT 1,
            show_connect tinyint(1) DEFAULT 1,
            custom_connect_link varchar(500) DEFAULT NULL,
            discord_invite varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_server_type (server_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql_servers);
        
        // Cache table
        $table_cache = self::get_table_name('cache');
        $sql_cache = "CREATE TABLE $table_cache (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            server_id bigint(20) UNSIGNED NOT NULL,
            cache_key varchar(255) NOT NULL,
            cache_data longtext NOT NULL,
            expiration datetime NOT NULL,
            PRIMARY KEY (id),
            KEY server_id (server_id),
            KEY cache_key (cache_key)
        ) $charset_collate;";
        
        dbDelta($sql_cache);
        
        // Statistics table
        $table_stats = self::get_table_name('statistics');
        $sql_stats = "CREATE TABLE $table_stats (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            server_id bigint(20) UNSIGNED NOT NULL,
            is_online tinyint(1) NOT NULL DEFAULT 0,
            players_online int(11) DEFAULT 0,
            players_max int(11) DEFAULT 0,
            map_name varchar(255) DEFAULT NULL,
            ping int(11) DEFAULT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY server_id (server_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($sql_stats);
        
        // Try to add is_active column if it doesn't exist
        self::addIsActiveColumn();
    }
    
    /**
     * Add is_active column if it doesn't exist
     */
    private static function addIsActiveColumn() {
        if (!self::column_exists('servers', 'is_active')) {
            global $wpdb;
            $table = self::get_table_name('servers');
            $wpdb->query("ALTER TABLE $table ADD COLUMN is_active tinyint(1) DEFAULT 1 AFTER discord_invite");
        }
    }
    
    /**
     * Drop all tables
     */
    public static function dropTables() {
        global $wpdb;
        
        $tables = array(
            self::get_table_name('servers'),
            self::get_table_name('cache'),
            self::get_table_name('statistics')
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Set default options
     */
    public static function setDefaultOptions() {
        add_option('gslm_default_refresh_interval', 60);
        add_option('gslm_default_auto_refresh', true);
        add_option('gslm_default_theme', 'modern');
        add_option('gslm_enable_cache', true);
        add_option('gslm_cache_duration', 300);
        add_option('gslm_enable_statistics', true);
        add_option('gslm_themes', array(
            'modern' => 'Modern',
            'dark' => 'Dark',
            'light' => 'Light',
            'glass' => 'Glassmorphism',
            'neon' => 'Neon'
        ));
    }
    
    /**
     * Remove all options
     */
    public static function removeOptions() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gslm_%'");
    }
    
    /**
     * Save server
     */
    public static function saveServer($data) {
        global $wpdb;
        
        $table = self::get_table_name('servers');
        
        $server_data = array(
            'server_name' => sanitize_text_field($data['server_name'] ?? ''),
            'server_type' => sanitize_text_field($data['server_type']),
            'server_ip' => sanitize_text_field($data['server_ip']),
            'server_port' => intval($data['server_port']),
            'query_port' => !empty($data['query_port']) ? intval($data['query_port']) : null,
            'game_icon' => sanitize_text_field($data['game_icon'] ?? ''),
            'custom_icon_url' => esc_url_raw($data['custom_icon_url'] ?? ''),
            'refresh_interval' => intval($data['refresh_interval'] ?? 60),
            'auto_refresh' => isset($data['auto_refresh']) ? 1 : 0,
            'theme_style' => sanitize_text_field($data['theme_style'] ?? 'modern'),
            'show_players' => isset($data['show_players']) ? 1 : 0,
            'show_map' => isset($data['show_map']) ? 1 : 0,
            'show_connect' => isset($data['show_connect']) ? 1 : 0,
            'custom_connect_link' => esc_url_raw($data['custom_connect_link'] ?? ''),
            'discord_invite' => sanitize_text_field($data['discord_invite'] ?? '')
        );
        
        // Add is_active only if column exists
        if (self::column_exists('servers', 'is_active')) {
            $server_data['is_active'] = isset($data['is_active']) ? 1 : 1; // Default to active
        }
        
        if (isset($data['id']) && $data['id'] > 0) {
            return $wpdb->update($table, $server_data, array('id' => $data['id']));
        } else {
            return $wpdb->insert($table, $server_data);
        }
    }
    
    /**
     * Get server by ID
     */
    public static function getServer($id) {
        global $wpdb;
        $table = self::get_table_name('servers');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Get all servers - FIXED to handle missing is_active column
     */
    public static function getServers($args = array()) {
        global $wpdb;
        $table = self::get_table_name('servers');
        
        $query = "SELECT * FROM $table";
        $where = array();
        $values = array();
        
        // Only add WHERE clause for type if specified
        if (isset($args['type']) && !empty($args['type'])) {
            $where[] = "server_type = %s";
            $values[] = $args['type'];
        }
        
        // Only check is_active if column exists and specifically requested
        if (isset($args['active']) && self::column_exists('servers', 'is_active')) {
            $where[] = "is_active = %d";
            $values[] = $args['active'] ? 1 : 0;
        }
        
        // Build WHERE clause
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        // Add ORDER BY
        $query .= " ORDER BY id DESC";
        
        // Add LIMIT
        if (isset($args['limit']) && $args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d", $args['limit']);
            
            if (isset($args['offset']) && $args['offset'] > 0) {
                $query .= $wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }
        
        // Prepare query if we have values
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Delete server
     */
    public static function deleteServer($id) {
        global $wpdb;
        
        $tables = array(
            'servers' => 'id',
            'cache' => 'server_id',
            'statistics' => 'server_id'
        );
        
        foreach ($tables as $table_name => $column) {
            $table = self::get_table_name($table_name);
            $wpdb->delete($table, array($column => $id));
        }
        
        return true;
    }
    
    /**
     * Update server status - only if column exists
     */
    public static function updateServerStatus($id, $active) {
        if (!self::column_exists('servers', 'is_active')) {
            return true; // Return true if column doesn't exist to avoid errors
        }
        
        global $wpdb;
        $table = self::get_table_name('servers');
        
        return $wpdb->update(
            $table,
            array('is_active' => $active ? 1 : 0),
            array('id' => intval($id))
        );
    }
    
    /**
     * Get cache
     */
    public static function getCache($server_id, $cache_key) {
        global $wpdb;
        $table = self::get_table_name('cache');
        
        $cache = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE server_id = %d AND cache_key = %s AND expiration > NOW()",
            $server_id,
            $cache_key
        ));
        
        return $cache ? json_decode($cache->cache_data, true) : false;
    }
    
    /**
     * Set cache
     */
    public static function setCache($server_id, $cache_key, $data, $duration = 300) {
        global $wpdb;
        $table = self::get_table_name('cache');
        
        // Delete old cache
        $wpdb->delete($table, array(
            'server_id' => $server_id,
            'cache_key' => $cache_key
        ));
        
        // Insert new cache
        return $wpdb->insert($table, array(
            'server_id' => $server_id,
            'cache_key' => $cache_key,
            'cache_data' => json_encode($data),
            'expiration' => date('Y-m-d H:i:s', time() + $duration)
        ));
    }
    
    /**
     * Clear cache
     */
    public static function clearCache($server_id = null) {
        global $wpdb;
        $table = self::get_table_name('cache');
        
        if ($server_id) {
            return $wpdb->delete($table, array('server_id' => $server_id));
        } else {
            return $wpdb->query("TRUNCATE TABLE $table");
        }
    }
}