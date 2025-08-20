
<?php
/**
 * Public Frontend Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_Public {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'));
    }
    
    public function enqueueAssets() {
        wp_register_style('gslm-public', GSLM_PLUGIN_URL . 'assets/css/public.css', array(), GSLM_VERSION);
        wp_register_script('gslm-public', GSLM_PLUGIN_URL . 'assets/js/public.js', array('jquery'), GSLM_VERSION, true);
        
        wp_localize_script('gslm-public', 'gslm_public', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gslm_public_nonce'),
            'loading_text' => __('Loading...', 'game-server-list-monitor'),
            'online_text' => __('ONLINE', 'game-server-list-monitor'),
            'offline_text' => __('OFFLINE', 'game-server-list-monitor'),
            'players_text' => __('Players', 'game-server-list-monitor'),
            'map_text' => __('Map', 'game-server-list-monitor'),
            'connect_text' => __('Connect', 'game-server-list-monitor'),
            'copied_text' => __('Copied!', 'game-server-list-monitor'),
            'error_text' => __('Error loading', 'game-server-list-monitor')
        ));
    }
}