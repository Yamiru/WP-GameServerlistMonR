<?php
/**
 * Plugin Name: GameServerlistMonR
 * Plugin URI: https://github.com/Yamiru/WP-GameServerlistMonR
 * Description: Beautiful minimalistic game server monitoring with real-time status updates. Monitor game servers, Discord communities, and voice servers directly from your WordPress site.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.0
 * Author: Yamiru
 * Author URI: https://yamiru.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gameserverlistMonR
 * Domain Path: /languages
 * 
 * @package GameServerlistMonR
 * @author Yamiru <yamiru@yamiru.com>
 * @copyright 2025 Yamiru
 * @license GPL-2.0-or-later
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * 
 * Third-party libraries:
 * This plugin optionally uses the GameQ PHP library (https://github.com/Austinb/GameQ)
 * by Austin B., which is licensed under the GNU Lesser General Public License v3.0.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

// Define plugin constants
define('GSLM_VERSION', '1.0.0');
define('GSLM_MINIMUM_WP_VERSION', '5.8');
define('GSLM_MINIMUM_PHP_VERSION', '7.0');
define('GSLM_PLUGIN_FILE', __FILE__);
define('GSLM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GSLM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GSLM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('GSLM_TEXT_DOMAIN', 'game-server-list-monitor');

/**
 * Check minimum requirements
 */
function gslm_check_requirements() {
    $errors = array();
    
    // Check PHP version
    if (version_compare(PHP_VERSION, GSLM_MINIMUM_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            /* translators: 1: Current PHP version, 2: Required PHP version */
            __('Game Server List Monitor requires PHP %2$s or higher. Your server is running PHP %1$s.', 'game-server-list-monitor'),
            PHP_VERSION,
            GSLM_MINIMUM_PHP_VERSION
        );
    }
    
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), GSLM_MINIMUM_WP_VERSION, '<')) {
        $errors[] = sprintf(
            /* translators: 1: Current WordPress version, 2: Required WordPress version */
            __('Game Server List Monitor requires WordPress %2$s or higher. You are running WordPress %1$s.', 'game-server-list-monitor'),
            get_bloginfo('version'),
            GSLM_MINIMUM_WP_VERSION
        );
    }
    
    // Check for required PHP extensions
    if (!function_exists('fsockopen')) {
        $errors[] = __('Game Server List Monitor requires the PHP sockets extension to be enabled.', 'game-server-list-monitor');
    }
    
    return $errors;
}

/**
 * Display admin notice for requirement errors
 */
function gslm_requirements_notice() {
    $errors = gslm_check_requirements();
    
    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p><strong>' . 
             esc_html__('Game Server List Monitor cannot be activated', 'game-server-list-monitor') . 
             '</strong></p><ul>';
        
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        
        echo '</ul></div>';
        
        // Deactivate plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Hide the "Plugin activated" notice
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

/**
 * Main Plugin Class
 */
class GameServerListMonitor {
    
    /**
     * Single instance of the class
     *
     * @var GameServerListMonitor
     */
    private static $instance = null;
    
    /**
     * Loaded classes tracking
     *
     * @var array
     */
    private $loaded_classes = array();
    
    /**
     * Get single instance of the class
     *
     * @return GameServerListMonitor
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Check requirements first
        if (!empty(gslm_check_requirements())) {
            add_action('admin_notices', 'gslm_requirements_notice');
            return;
        }
        
        $this->load_dependencies();
        $this->register_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load text domain early for translations
        add_action('init', array($this, 'load_textdomain'), 1);
        
        // Core files - load in correct order
        $core_files = array(
            'includes/class-gslm-database.php' => 'GSLM_Database',
            'includes/class-gslm-helper.php' => 'GSLM_Helper',
            'includes/class-gslm-gamequerylib.php' => 'GSLM_GameQueryLib',
            'includes/class-gslm-tools.php' => 'GSLM_Tools'
        );
        
        foreach ($core_files as $file => $class_name) {
            $file_path = GSLM_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->loaded_classes[] = $class_name;
            } else {
                $this->log_error(sprintf('Required file not found: %s', $file));
            }
        }
        
        // Admin files
        if (is_admin()) {
            $this->load_admin_dependencies();
        }
        
        // Public files
        $this->load_public_dependencies();
        
        // Load GameQ installer if needed
        $installer_file = GSLM_PLUGIN_DIR . 'includes/class-gslm-gameq-installer.php';
        if (file_exists($installer_file)) {
            require_once $installer_file;
            $this->loaded_classes[] = 'GSLM_GameQ_Installer';
        }
    }
    
    /**
     * Load admin dependencies
     */
    private function load_admin_dependencies() {
        $admin_file = GSLM_PLUGIN_DIR . 'includes/class-gslm-admin.php';
        if (file_exists($admin_file)) {
            require_once $admin_file;
            new GSLM_Admin();
            $this->loaded_classes[] = 'GSLM_Admin';
        } else {
            $this->log_error('Admin file not found');
        }
    }
    
    /**
     * Load public dependencies
     */
    private function load_public_dependencies() {
        $public_files = array(
            'includes/class-gslm-public.php' => 'GSLM_Public',
            'includes/class-gslm-shortcode.php' => 'GSLM_Shortcode',
            'includes/class-gslm-ajax.php' => 'GSLM_Ajax'
        );
        
        foreach ($public_files as $file => $class_name) {
            $file_path = GSLM_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                
                if (class_exists($class_name)) {
                    new $class_name();
                    $this->loaded_classes[] = $class_name;
                } else {
                    $this->log_error(sprintf('Class not found after including file: %s', $class_name));
                }
            } else {
                $this->log_error(sprintf('Required file not found: %s', $file));
            }
        }
    }
    
    /**
     * Register plugin hooks
     */
    private function register_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(GSLM_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(GSLM_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . GSLM_PLUGIN_BASENAME, array($this, 'add_action_links'));
        add_filter('plugin_row_meta', array($this, 'add_row_meta'), 10, 2);
        
        // Check for database updates
        add_action('admin_init', array($this, 'check_database_updates'));
        
        // Register uninstall hook
        register_uninstall_hook(GSLM_PLUGIN_FILE, array(__CLASS__, 'uninstall'));
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            GSLM_TEXT_DOMAIN,
            false,
            dirname(GSLM_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements
        $errors = gslm_check_requirements();
        if (!empty($errors)) {
            wp_die(implode('<br>', $errors));
        }
        
        // Create tables and set default options
        if (class_exists('GSLM_Database')) {
            GSLM_Database::createTables();
            GSLM_Database::setDefaultOptions();
        }
        
        // Update database schema if needed
        $this->update_database_schema();
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('gslm_activated', true);
        update_option('gslm_activation_time', current_time('mysql'));
        update_option('gslm_version', GSLM_VERSION);
        
        // Schedule cron events
        $this->schedule_cron_events();
        
        // Clear cache
        wp_cache_flush();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('gslm_cleanup_old_data');
        wp_clear_scheduled_hook('gslm_refresh_cache');
        wp_clear_scheduled_hook('gslm_optimize_database');
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Clear cache
        wp_cache_flush();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Only run if explicitly uninstalling
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Load uninstall file
        $uninstall_file = GSLM_PLUGIN_DIR . 'uninstall.php';
        if (file_exists($uninstall_file)) {
            require_once $uninstall_file;
        }
    }
    
    /**
     * Schedule cron events
     */
    private function schedule_cron_events() {
        // Schedule daily cleanup
        if (!wp_next_scheduled('gslm_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'gslm_cleanup_old_data');
        }
        
        // Schedule hourly cache refresh
        if (!wp_next_scheduled('gslm_refresh_cache')) {
            wp_schedule_event(time(), 'hourly', 'gslm_refresh_cache');
        }
        
        // Schedule weekly database optimization
        if (!wp_next_scheduled('gslm_optimize_database')) {
            wp_schedule_event(time(), 'weekly', 'gslm_optimize_database');
        }
    }
    
    /**
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $action_links = array(
            '<a href="' . esc_url(admin_url('admin.php?page=gameserverlistmonr')) . '">' . 
                esc_html__('Dashboard', 'game-server-list-monitor') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=gslm-settings')) . '">' . 
                esc_html__('Settings', 'game-server-list-monitor') . '</a>',
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Add plugin row meta
     *
     * @param array  $links Existing links
     * @param string $file  Plugin file
     * @return array Modified links
     */
    public function add_row_meta($links, $file) {
        if (GSLM_PLUGIN_BASENAME === $file) {
            $row_meta = array(
                'docs' => '<a href="' . esc_url('https://github.com/Yamiru/WP-GameServerlistMonR/wiki') . '" target="_blank">' . 
                    esc_html__('Documentation', 'game-server-list-monitor') . '</a>',
                'support' => '<a href="' . esc_url('https://github.com/Yamiru/WP-GameServerlistMonR/issues') . '" target="_blank">' . 
                    esc_html__('Support', 'game-server-list-monitor') . '</a>',
            );
            
            return array_merge($links, $row_meta);
        }
        
        return $links;
    }
    
    /**
     * Check and update database schema
     */
    public function check_database_updates() {
        $current_version = get_option('gslm_version', '0');
        
        if (version_compare($current_version, GSLM_VERSION, '<')) {
            $this->update_database_schema();
            update_option('gslm_version', GSLM_VERSION);
        }
    }
    
    /**
     * Update database schema
     */
    private function update_database_schema() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gslm_servers';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        )) === $table;
        
        if (!$table_exists) {
            // Create table if it doesn't exist
            if (class_exists('GSLM_Database')) {
                GSLM_Database::createTables();
            }
            return;
        }
        
        // Check if discord_invite column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM %i LIKE %s",
            $table,
            'discord_invite'
        ));
        
        if (empty($column_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "ALTER TABLE %i ADD COLUMN discord_invite VARCHAR(255) DEFAULT NULL AFTER custom_connect_link",
                    $table
                )
            );
        }
        
        // Update any Discord servers to use proper format
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET server_ip = 'discord', server_port = 0 WHERE LOWER(server_type) = 'discord' AND server_ip != 'discord'",
                $table
            )
        );
    }
    
    /**
     * Log errors for debugging
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[GSLM Error] ' . $message);
        }
    }
    
    /**
     * Get loaded classes (for debugging)
     *
     * @return array List of loaded classes
     */
    public function get_loaded_classes() {
        return $this->loaded_classes;
    }
}

/**
 * Initialize plugin
 *
 * @return GameServerListMonitor Plugin instance
 */
function gslm_init() {
    return GameServerListMonitor::get_instance();
}

// Hook to plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'gslm_init');

// Register cron action handlers
add_action('gslm_cleanup_old_data', 'gslm_cleanup_old_data');
add_action('gslm_refresh_cache', 'gslm_refresh_cache');
add_action('gslm_optimize_database', 'gslm_optimize_database');

/**
 * Cleanup old data cron handler
 */
function gslm_cleanup_old_data() {
    if (class_exists('GSLM_Tools')) {
        GSLM_Tools::cleanupStatistics(30);
    }
}

/**
 * Refresh cache cron handler
 */
function gslm_refresh_cache() {
    if (class_exists('GSLM_Database')) {
        GSLM_Database::clearCache();
    }
}

/**
 * Optimize database cron handler
 */
function gslm_optimize_database() {
    if (class_exists('GSLM_Tools')) {
        GSLM_Tools::optimizeTables();
    }
}