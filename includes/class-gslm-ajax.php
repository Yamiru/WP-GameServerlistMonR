<?php
/**
 * AJAX Handler Class for GameServerlistMonR
 * 
 * @package GameServerlistMonR
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GSLM_Ajax
 * Handles all AJAX requests with proper security
 */
class GSLM_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Public AJAX handlers
        add_action('wp_ajax_gslm_get_server_status', array($this, 'get_server_status'));
        add_action('wp_ajax_nopriv_gslm_get_server_status', array($this, 'get_server_status'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_gslm_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_gslm_test_server', array($this, 'test_server'));
        add_action('wp_ajax_gslm_install_gameq', array($this, 'install_gameq'));
        add_action('wp_ajax_gslm_bulk_action', array($this, 'bulk_action'));
    }
    
    /**
     * Get server status via AJAX
     */
    public function get_server_status() {
        // Verify nonce
        if (!$this->verify_nonce('gslm_public_nonce', 'gslm_admin_nonce')) {
            wp_send_json_error(
                array('message' => __('Security verification failed', 'game-server-list-monitor')),
                403
            );
        }
        
        // Validate and sanitize input
        $server_id = isset($_POST['server_id']) ? absint($_POST['server_id']) : 0;
        
        if (!$server_id) {
            wp_send_json_error(
                array('message' => __('Invalid server ID', 'game-server-list-monitor')),
                400
            );
        }
        
        // Rate limiting
        $this->check_rate_limit('server_status_' . $server_id, 10, 60);
        
        // Get server from database
        $server = GSLM_Database::getServer($server_id);
        
        if (!$server) {
            wp_send_json_error(
                array('message' => __('Server not found', 'game-server-list-monitor')),
                404
            );
        }
        
        // Check cache first
        $cache_key = 'gslm_status_' . $server_id;
        $cached = wp_cache_get($cache_key, 'gslm');
        
        if (false !== $cached && get_option('gslm_enable_cache', true)) {
            $cached['from_cache'] = true;
            wp_send_json_success($cached);
        }
        
        // Query server
        $status = GSLM_Helper::queryServer($server);
        
        // Sanitize output
        $status = $this->sanitize_server_status($status);
        
        // Save to cache
        if (get_option('gslm_enable_cache', true)) {
            $cache_duration = absint(get_option('gslm_cache_duration', 300));
            wp_cache_set($cache_key, $status, 'gslm', $cache_duration);
            GSLM_Database::setCache($server_id, 'status', $status, $cache_duration);
        }
        
        // Add connect link
        $status['connect_link'] = GSLM_Helper::getConnectLink($server);
        
        wp_send_json_success($status);
    }
    
    /**
     * Clear cache (admin only)
     */
    public function clear_cache() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('Insufficient permissions', 'game-server-list-monitor')),
                403
            );
        }
        
        // Verify nonce
        if (!$this->verify_nonce('gslm_admin_nonce')) {
            wp_send_json_error(
                array('message' => __('Security verification failed', 'game-server-list-monitor')),
                403
            );
        }
        
        // Clear database cache
        GSLM_Database::clearCache();
        
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_gslm_') . '%',
                $wpdb->esc_like('_transient_timeout_gslm_') . '%'
            )
        );
        
        wp_send_json_success(
            array('message' => __('Cache cleared successfully', 'game-server-list-monitor'))
        );
    }
    
    /**
     * Test server connection (admin only)
     */
    public function test_server() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('Insufficient permissions', 'game-server-list-monitor')),
                403
            );
        }
        
        // Verify nonce
        if (!$this->verify_nonce('gslm_admin_nonce', 'gslm_test_nonce')) {
            wp_send_json_error(
                array('message' => __('Security verification failed', 'game-server-list-monitor')),
                403
            );
        }
        
        // Sanitize input
        $server_type = isset($_POST['server_type']) ? sanitize_text_field($_POST['server_type']) : '';
        $server_ip = isset($_POST['server_ip']) ? sanitize_text_field($_POST['server_ip']) : '';
        $server_port = isset($_POST['server_port']) ? absint($_POST['server_port']) : 0;
        $query_port = isset($_POST['query_port']) ? absint($_POST['query_port']) : 0;
        $discord_invite = isset($_POST['discord_invite']) ? sanitize_text_field($_POST['discord_invite']) : '';
        
        // Validate required fields
        if (empty($server_type)) {
            wp_send_json_error(
                array('message' => __('Server type is required', 'game-server-list-monitor')),
                400
            );
        }
        
        // Create temporary server object
        $test_server = (object) array(
            'id' => 0,
            'server_name' => __('Test Server', 'game-server-list-monitor'),
            'server_type' => $server_type,
            'server_ip' => $server_ip,
            'server_port' => $server_port,
            'query_port' => $query_port ?: null,
            'discord_invite' => $discord_invite,
            'show_players' => 1,
            'show_map' => 1,
            'show_connect' => 1
        );
        
        // Test the connection
        $status = GSLM_Helper::queryServer($test_server);
        
        // Sanitize output
        $status = $this->sanitize_server_status($status);
        
        // Add connect link
        $status['connect_link'] = GSLM_Helper::getConnectLink($test_server);
        
        wp_send_json_success($status);
    }
    
    /**
     * Install GameQ library (admin only)
     */
    public function install_gameq() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('Insufficient permissions', 'game-server-list-monitor')),
                403
            );
        }
        
        // Verify nonce
        if (!$this->verify_nonce('gslm_admin_nonce')) {
            wp_send_json_error(
                array('message' => __('Security verification failed', 'game-server-list-monitor')),
                403
            );
        }
        
        // Rate limiting for installation
        $this->check_rate_limit('gameq_install', 1, 300);
        
        // Check if already installing
        $install_lock = get_transient('gslm_gameq_installing');
        if (false !== $install_lock) {
            wp_send_json_error(
                array('message' => __('Installation already in progress', 'game-server-list-monitor')),
                429
            );
        }
        
        // Set installation lock
        set_transient('gslm_gameq_installing', true, 120);
        
        try {
            // Load installer class
            if (!class_exists('GSLM_GameQ_Installer')) {
                $installer_file = GSLM_PLUGIN_DIR . 'includes/class-gslm-gameq-installer.php';
                if (file_exists($installer_file)) {
                    require_once $installer_file;
                }
            }
            
            if (class_exists('GSLM_GameQ_Installer')) {
                $result = GSLM_GameQ_Installer::install();
            } else {
                $result = array(
                    'success' => false,
                    'message' => __('Installer class not found', 'game-server-list-monitor')
                );
            }
            
            // Clear the installation lock
            delete_transient('gslm_gameq_installing');
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result, 500);
            }
            
        } catch (Exception $e) {
            // Clear locks on exception
            delete_transient('gslm_gameq_installing');
            
            wp_send_json_error(
                array('message' => sprintf(
                    /* translators: %s: Error message */
                    __('Installation error: %s', 'game-server-list-monitor'),
                    $e->getMessage()
                )),
                500
            );
        }
    }
    
    /**
     * Handle bulk actions (admin only)
     */
    public function bulk_action() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('Insufficient permissions', 'game-server-list-monitor')),
                403
            );
        }
        
        // Verify nonce
        if (!$this->verify_nonce('gslm_admin_nonce')) {
            wp_send_json_error(
                array('message' => __('Security verification failed', 'game-server-list-monitor')),
                403
            );
        }
        
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $server_ids = isset($_POST['server_ids']) ? array_map('absint', (array) $_POST['server_ids']) : array();
        
        if (empty($action) || empty($server_ids)) {
            wp_send_json_error(
                array('message' => __('Invalid parameters', 'game-server-list-monitor')),
                400
            );
        }
        
        $results = array();
        
        switch ($action) {
            case 'delete':
                foreach ($server_ids as $server_id) {
                    if (GSLM_Database::deleteServer($server_id)) {
                        $results[] = $server_id;
                    }
                }
                $message = sprintf(
                    /* translators: %d: Number of deleted servers */
                    _n('%d server deleted', '%d servers deleted', count($results), 'game-server-list-monitor'),
                    count($results)
                );
                break;
                
            case 'enable':
                foreach ($server_ids as $server_id) {
                    if (GSLM_Database::updateServerStatus($server_id, true)) {
                        $results[] = $server_id;
                    }
                }
                $message = sprintf(
                    /* translators: %d: Number of enabled servers */
                    _n('%d server enabled', '%d servers enabled', count($results), 'game-server-list-monitor'),
                    count($results)
                );
                break;
                
            case 'disable':
                foreach ($server_ids as $server_id) {
                    if (GSLM_Database::updateServerStatus($server_id, false)) {
                        $results[] = $server_id;
                    }
                }
                $message = sprintf(
                    /* translators: %d: Number of disabled servers */
                    _n('%d server disabled', '%d servers disabled', count($results), 'game-server-list-monitor'),
                    count($results)
                );
                break;
                
            default:
                wp_send_json_error(
                    array('message' => __('Invalid action', 'game-server-list-monitor')),
                    400
                );
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'affected' => $results
        ));
    }
    
    /**
     * Verify nonce from multiple possible sources
     *
     * @param string ...$nonce_names Nonce names to check
     * @return bool
     */
    private function verify_nonce(...$nonce_names) {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        
        if (empty($nonce)) {
            return false;
        }
        
        foreach ($nonce_names as $nonce_name) {
            if (wp_verify_nonce($nonce, $nonce_name)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check rate limiting
     *
     * @param string $action Action identifier
     * @param int    $limit  Maximum attempts
     * @param int    $window Time window in seconds
     */
    private function check_rate_limit($action, $limit, $window) {
        $ip = $this->get_client_ip();
        $key = 'gslm_rate_' . md5($action . $ip);
        
        $attempts = get_transient($key);
        
        if (false === $attempts) {
            set_transient($key, 1, $window);
        } else {
            if ($attempts >= $limit) {
                wp_send_json_error(
                    array('message' => __('Rate limit exceeded. Please try again later.', 'game-server-list-monitor')),
                    429
                );
            }
            set_transient($key, $attempts + 1, $window);
        }
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                $ip = explode(',', $ip);
                $ip = trim($ip[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Sanitize server status output
     *
     * @param array $status Status data
     * @return array Sanitized status
     */
    private function sanitize_server_status($status) {
        $sanitized = array();
        
        // Boolean fields
        $bool_fields = array('online', 'gq_online');
        foreach ($bool_fields as $field) {
            $sanitized[$field] = isset($status[$field]) ? (bool) $status[$field] : false;
        }
        
        // String fields
        $string_fields = array('gq_hostname', 'gq_gametype', 'gq_mapname', 'hostname', 'map', 'server_type', 'version', 'error', 'note');
        foreach ($string_fields as $field) {
            if (isset($status[$field])) {
                $sanitized[$field] = sanitize_text_field($status[$field]);
            }
        }
        
        // Integer fields
        $int_fields = array('gq_numplayers', 'gq_maxplayers', 'players', 'max_players');
        foreach ($int_fields as $field) {
            if (isset($status[$field])) {
                $sanitized[$field] = absint($status[$field]);
            }
        }
        
        // URL fields
        if (isset($status['discord_invite'])) {
            $sanitized['discord_invite'] = esc_url_raw($status['discord_invite']);
        }
        
        return $sanitized;
    }
}