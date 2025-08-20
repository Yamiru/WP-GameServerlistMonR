<?php
/**
 * Shortcode Handler Class for GameServerlistMonR
 * File: includes/class-gslm-shortcode.php
 * 
 * @package GameServerlistMonR
 * @version 1.0.0
 * Powered by GameServerlistMonR - A project by Yamiru (yamiru.com)
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_Shortcode {
    
    public function __construct() {
        add_shortcode('monr_list', array($this, 'serverListShortcode'));
        add_shortcode('game_server', array($this, 'singleServerShortcode'));
    }
    
    public function serverListShortcode($atts) {
        wp_enqueue_style('gslm-public');
        wp_enqueue_script('gslm-public');
        
        $atts = shortcode_atts(array(
            'type' => '',
            'theme' => get_option('gslm_default_theme', 'modern'),
            'show_offline' => 'yes',
            'show_powered' => 'yes'
        ), $atts, 'monr_list');
        
        $args = array();
        if (!empty($atts['type'])) {
            $args['type'] = $atts['type'];
        }
        
        $servers = GSLM_Database::getServers($args);
        
        if (empty($servers)) {
            return '<div class="gslm-notice">No servers found. Add servers in the admin panel.</div>';
        }
        
        ob_start();
        ?>
        <div class="gslm-servers-container gslm-theme-<?php echo esc_attr($atts['theme']); ?> <?php echo $atts['show_offline'] === 'no' ? 'gslm-hide-offline' : ''; ?>" data-theme="<?php echo esc_attr($atts['theme']); ?>">
            <div class="gslm-servers-list">
                <?php foreach ($servers as $server): 
                    $server_type = strtolower($server->server_type);
                    $is_discord = ($server_type === 'discord');
                    
                    // Check if we should show icon
                    $show_icon = !empty($server->custom_icon_url) || !empty($server->game_icon);
                    
                    // Format address display
                    if ($is_discord) {
                        // For Discord, use the invite link directly
                        $display_address = !empty($server->discord_invite) ? $server->discord_invite : '';
                        // Clean up for display
                        if (strpos($display_address, 'discord.gg/') !== false) {
                            $display_address = str_replace(['https://discord.gg/', 'http://discord.gg/'], 'discord.gg/', $display_address);
                        } elseif (!empty($display_address) && strpos($display_address, 'discord.gg') === false) {
                            $display_address = 'discord.gg/' . $display_address;
                        }
                    } else {
                        $display_address = $server->server_ip;
                        // Add port if not default
                        if ($server->server_port) {
                            if (!filter_var($server->server_ip, FILTER_VALIDATE_IP)) {
                                // Domain - only add port if not default
                                if ($server->server_port != 25565 || $server_type !== 'minecraft') {
                                    $display_address .= ':' . $server->server_port;
                                }
                            } else {
                                // IP - always add port
                                $display_address .= ':' . $server->server_port;
                            }
                        }
                    }
                ?>
                    <div class="gslm-server-row server-type-<?php echo esc_attr($server_type); ?>" 
                         data-server-id="<?php echo esc_attr($server->id); ?>"
                         data-refresh="<?php echo $server->auto_refresh ? esc_attr($server->refresh_interval) : '0'; ?>"
                         data-status="checking">
                        
                        <?php if ($show_icon): ?>
                        <div class="gslm-server-icon">
                            <?php if (!empty($server->custom_icon_url)): ?>
                                <img src="<?php echo esc_url($server->custom_icon_url); ?>" alt="">
                            <?php elseif (!empty($server->game_icon)): ?>
                                <span style="font-size: 24px;"><?php echo esc_html($server->game_icon); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="gslm-server-icon no-icon"></div>
                        <?php endif; ?>
                        
                        <div class="gslm-status-indicator" data-status="checking"></div>
                        
                        <h3 class="gslm-server-name" data-original="<?php echo esc_attr($server->server_name); ?>">
                            <?php echo esc_html($server->server_name); ?>
                        </h3>
                        
                        <span class="gslm-server-address" data-copy="<?php echo esc_attr($display_address); ?>">
                            <?php echo esc_html($display_address); ?>
                        </span>
                        
                        <div class="gslm-server-info">
                            <?php if (!$is_discord): ?>
                            <div class="gslm-info-item gslm-game-type">
                                <span class="gslm-info-label">Type:</span>
                                <span class="gslm-info-value gslm-gametype"><?php echo esc_html(GSLM_Helper::getServerTypeDisplay($server->server_type)); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($server->show_map): ?>
                            <div class="gslm-info-item gslm-map-info" style="display: none;">
                                <span class="gslm-info-label">Map:</span>
                                <span class="gslm-info-value gslm-map-name">—</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($server->show_players): ?>
                        <div class="gslm-players" style="display: none;">
                            <span class="gslm-players-count">—</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($server->show_connect): ?>
                        <a href="#" class="gslm-connect-btn" style="display: none;" target="_blank">
                            <?php echo $is_discord ? 'JOIN' : 'CONNECT'; ?>
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($atts['show_powered'] === 'yes'): ?>
            <div class="gslm-powered">
                Powered by <a href="https://github.com/Yamiru/WP-GameServerlistMonR" target="_blank">MonR</a> 
                - A project by <a href="https://yamiru.com" target="_blank"><strong>Yamiru</strong></a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    public function singleServerShortcode($atts) {
        wp_enqueue_style('gslm-public');
        wp_enqueue_script('gslm-public');
        
        $atts = shortcode_atts(array(
            'id' => '',
            'theme' => get_option('gslm_default_theme', 'modern'),
            'show_powered' => 'no'
        ), $atts, 'game_server');
        
        if (empty($atts['id'])) {
            return '<div class="gslm-error">Server ID required</div>';
        }
        
        $server = GSLM_Database::getServer(intval($atts['id']));
        
        if (!$server) {
            return '<div class="gslm-error">Server not found</div>';
        }
        
        ob_start();
        
        $server_type = strtolower($server->server_type);
        $is_discord = ($server_type === 'discord');
        $show_icon = !empty($server->custom_icon_url) || !empty($server->game_icon);
        
        // Format address display
        if ($is_discord) {
            $display_address = !empty($server->discord_invite) ? $server->discord_invite : '';
            if (strpos($display_address, 'discord.gg/') !== false) {
                $display_address = str_replace(['https://discord.gg/', 'http://discord.gg/'], 'discord.gg/', $display_address);
            } elseif (!empty($display_address) && strpos($display_address, 'discord.gg') === false) {
                $display_address = 'discord.gg/' . $display_address;
            }
        } else {
            $display_address = $server->server_ip;
            if ($server->server_port) {
                if (!filter_var($server->server_ip, FILTER_VALIDATE_IP)) {
                    if ($server->server_port != 25565 || $server_type !== 'minecraft') {
                        $display_address .= ':' . $server->server_port;
                    }
                } else {
                    $display_address .= ':' . $server->server_port;
                }
            }
        }
        ?>
        <div class="gslm-servers-container gslm-theme-<?php echo esc_attr($atts['theme']); ?>" data-theme="<?php echo esc_attr($atts['theme']); ?>">
            <div class="gslm-servers-list">
                <div class="gslm-server-row server-type-<?php echo esc_attr($server_type); ?>" 
                     data-server-id="<?php echo esc_attr($server->id); ?>"
                     data-refresh="<?php echo $server->auto_refresh ? esc_attr($server->refresh_interval) : '0'; ?>"
                     data-status="checking">
                    
                    <?php if ($show_icon): ?>
                    <div class="gslm-server-icon">
                        <?php if (!empty($server->custom_icon_url)): ?>
                            <img src="<?php echo esc_url($server->custom_icon_url); ?>" alt="">
                        <?php elseif (!empty($server->game_icon)): ?>
                            <span style="font-size: 24px;"><?php echo esc_html($server->game_icon); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="gslm-server-icon no-icon"></div>
                    <?php endif; ?>
                    
                    <div class="gslm-status-indicator" data-status="checking"></div>
                    
                    <h3 class="gslm-server-name" data-original="<?php echo esc_attr($server->server_name); ?>">
                        <?php echo esc_html($server->server_name); ?>
                    </h3>
                    
                    <span class="gslm-server-address" data-copy="<?php echo esc_attr($display_address); ?>">
                        <?php echo esc_html($display_address); ?>
                    </span>
                    
                    <div class="gslm-server-info">
                        <?php if (!$is_discord): ?>
                        <div class="gslm-info-item gslm-game-type">
                            <span class="gslm-info-label">Type:</span>
                            <span class="gslm-info-value gslm-gametype"><?php echo esc_html(GSLM_Helper::getServerTypeDisplay($server->server_type)); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($server->show_map): ?>
                        <div class="gslm-info-item gslm-map-info" style="display: none;">
                            <span class="gslm-info-label">Map:</span>
                            <span class="gslm-info-value gslm-map-name">—</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($server->show_players): ?>
                    <div class="gslm-players" style="display: none;">
                        <span class="gslm-players-count">—</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($server->show_connect): ?>
                    <a href="#" class="gslm-connect-btn" style="display: none;" target="_blank">
                        <?php echo $is_discord ? 'JOIN' : 'CONNECT'; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($atts['show_powered'] === 'yes'): ?>
            <div class="gslm-powered">
                Powered by <a href="https://github.com/Yamiru/WP-GameServerlistMonR" target="_blank">MonR</a> 
                - A project by <a href="https://yamiru.com" target="_blank"><strong>Yamiru</strong></a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}