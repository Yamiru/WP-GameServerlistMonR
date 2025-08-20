<?php
/**
 * Admin Class for GameServerlistMonR
 * File: includes/class-gslm-admin.php
 * 
 * @package GameServerlistMonR
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('admin_init', array($this, 'handleActions'));
        add_action('admin_notices', array($this, 'displayNotices'));
        
        // AJAX handlers for GameQ
        add_action('wp_ajax_gslm_install_gameq', array($this, 'ajaxInstallGameQ'));
    }
    
    public function addAdminMenu() {
        // Main menu
        add_menu_page(
            'Game Server Monitor',
            'Game Servers',
            'manage_options',
            'gameserverlistmonr',
            array($this, 'dashboardPage'),
            'dashicons-games',
            30
        );
        
        // Dashboard
        add_submenu_page(
            'gameserverlistmonr',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'gameserverlistmonr',
            array($this, 'dashboardPage')
        );
        
        // Servers list
        add_submenu_page(
            'gameserverlistmonr',
            'All Servers',
            'All Servers',
            'manage_options',
            'gslm-servers',
            array($this, 'serversPage')
        );
        
        // Add new server
        add_submenu_page(
            'gameserverlistmonr',
            'Add Server',
            'Add New',
            'manage_options',
            'gslm-add-server',
            array($this, 'addServerPage')
        );
        
        // Settings
        add_submenu_page(
            'gameserverlistmonr',
            'Settings',
            'Settings',
            'manage_options',
            'gslm-settings',
            array($this, 'settingsPage')
        );
        
        // Tools
        add_submenu_page(
            'gameserverlistmonr',
            'Tools',
            'Tools',
            'manage_options',
            'gslm-tools',
            array($this, 'toolsPage')
        );
    }
    
    public function enqueueAssets($hook) {
        if (strpos($hook, 'gameserverlistmonr') === false && strpos($hook, 'gslm-') === false) {
            return;
        }
        
        wp_enqueue_style('gslm-admin', GSLM_PLUGIN_URL . 'assets/css/admin.css', array(), GSLM_VERSION);
        wp_enqueue_script('gslm-admin', GSLM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), GSLM_VERSION, true);
        
        wp_localize_script('gslm-admin', 'gslm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gslm_admin_nonce'),
            'installing_text' => __('Installing GameQ...', 'game-server-list-monitor'),
            'install_success' => __('GameQ installed successfully!', 'game-server-list-monitor'),
            'install_failed' => __('Installation failed. Please try manual installation.', 'game-server-list-monitor')
        ));
    }
    
    public function handleActions() {
        // Handle purge from plugin action link
        if (isset($_GET['page']) && $_GET['page'] == 'gslm-tools' && isset($_GET['action']) && $_GET['action'] == 'purge') {
            GSLM_Tools::reinstallPlugin();
            wp_redirect(admin_url('admin.php?page=gameserverlistmonr&purged=1'));
            exit;
        }
        
        // Handle form submissions
        if (isset($_POST['gslm_action'])) {
            $action = sanitize_text_field($_POST['gslm_action']);
            
            // Verify nonce
            if (!isset($_POST['gslm_nonce']) || !wp_verify_nonce($_POST['gslm_nonce'], 'gslm_admin_action')) {
                wp_die('Security check failed');
            }
            
            switch ($action) {
                case 'save_server':
                    $this->saveServer();
                    break;
                case 'delete_server':
                    $this->deleteServer();
                    break;
                case 'save_settings':
                    $this->saveSettings();
                    break;
                case 'reinstall_plugin':
                    $this->reinstallPlugin();
                    break;
                case 'clear_all_data':
                    $this->clearAllData();
                    break;
                case 'install_gameq':
                    $this->installGameQ();
                    break;
            }
        }
    }
    
    public function dashboardPage() {
        $servers = GSLM_Database::getServers();
        $online_count = 0;
        $total_players = 0;
        $gameq_status = GSLM_Helper::getGameQStatus();
        
        // Check if database was purged
        if (isset($_GET['purged'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Database has been purged and reinstalled successfully!</p></div>';
        }
        ?>
        <div class="wrap gslm-admin-wrap">
            <h1><span class="dashicons dashicons-games"></span> Game Server Monitor Dashboard</h1>
            
            <?php if (!$gameq_status['installed']): ?>
            <div class="notice notice-warning">
                <p><strong>GameQ Library Not Installed</strong> - <a href="https://github.com/Austinb/GameQ" target="_blank" style="color: #3b82f6; text-decoration: none;">GameQ PHP library</a> 
                    by Austin B., which is licensed under the GNU Lesser General Public License v3.0.
</p>
                <p>Install the official GameQ library for better server query support and more game protocols.</p>
                <p>
                    <button id="install-gameq-btn" class="button button-primary">
                        <span class="dashicons dashicons-download"></span> Install GameQ Automatically
                    </button>
                    <span class="spinner" style="display: none; float: none;"></span>
                </p>
                <div id="gameq-install-result" style="display: none; margin-top: 10px;"></div>
            </div>
            <?php else: ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✓ GameQ Library Installed</strong> - This plugin uses the <a href="https://github.com/Austinb/GameQ" target="_blank" style="color: #3b82f6; text-decoration: none;">GameQ PHP library</a> 
                    by Austin B., which is licensed under the GNU Lesser General Public License v3.0.
 </p>
            </div>
            <?php endif; ?>
            
            <div class="gslm-widget-row">
                <div class="gslm-stat-widget">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-site"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($servers); ?></div>
                        <div class="stat-label">Total Servers</div>
                    </div>
                </div>
                
                <div class="gslm-stat-widget online">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="online-servers">0</div>
                        <div class="stat-label">Online Servers</div>
                    </div>
                </div>
                
                <div class="gslm-stat-widget players">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="total-players">0</div>
                        <div class="stat-label">Total Players</div>
                    </div>
                </div>
                
                <div class="gslm-stat-widget uptime">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="uptime-percent">0%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                </div>
            </div>
            
            <div class="gslm-quick-actions">
                <h2>Quick Actions</h2>
                <a href="<?php echo admin_url('admin.php?page=gslm-add-server'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> Add New Server
                </a>
                <a href="<?php echo admin_url('admin.php?page=gslm-servers'); ?>" class="button">
                    <span class="dashicons dashicons-list-view"></span> View All Servers
                </a>
                <a href="<?php echo admin_url('admin.php?page=gslm-settings'); ?>" class="button">
                    <span class="dashicons dashicons-admin-settings"></span> Settings
                </a>
                <button id="clear_all_cache" class="button">
                    <span class="dashicons dashicons-trash"></span> Clear Cache
                </button>
            </div>
            
            <?php if (!empty($servers)): ?>
            <div class="gslm-recent-servers">
                <h2>Recent Servers</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;">Icon</th>
                            <th>Server Name</th>
                            <th>Type</th>
                            <th>Address</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 100px;">Players</th>
                            <th>Map</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($servers, 0, 5) as $server): 
                            $server_type = strtolower($server->server_type);
                            $is_discord = ($server_type === 'discord');
                            
                            // Format address
                            if ($is_discord) {
                                $display_address = !empty($server->discord_invite) ? 'discord.gg/...' : 'Discord';
                            } else {
                                $display_address = $server->server_ip . ':' . $server->server_port;
                            }
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($server->custom_icon_url)): ?>
                                    <img src="<?php echo esc_url($server->custom_icon_url); ?>" style="width: 24px; height: 24px;">
                                <?php elseif (!empty($server->game_icon)): ?>
                                    <span style="font-size: 20px;"><?php echo esc_html($server->game_icon); ?></span>
                                <?php else: ?>
                                    <span class="server-status-icon" data-server-id="<?php echo $server->id; ?>" style="font-size: 16px;">⚪</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="server-hostname" data-server-id="<?php echo $server->id; ?>" data-original="<?php echo esc_attr($server->server_name); ?>">
                                    <?php echo esc_html($server->server_name ?: 'Loading...'); ?>
                                </strong>
                            </td>
                            <td class="server-gametype" data-server-id="<?php echo $server->id; ?>" data-original="<?php echo esc_attr($server->server_type); ?>">
                                <?php echo esc_html(GSLM_Helper::getServerTypeDisplay($server->server_type)); ?>
                            </td>
                            <td><?php echo esc_html($display_address); ?></td>
                            <td class="gslm-status-check" data-server-id="<?php echo $server->id; ?>">
                                <span style="color: #999;">Checking...</span>
                            </td>
                            <td class="gslm-players-check" data-server-id="<?php echo $server->id; ?>">—</td>
                            <td class="gslm-map-check" data-server-id="<?php echo $server->id; ?>">—</td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=gslm-add-server&id=' . $server->id); ?>" class="button button-small">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; opacity: 0.7;">
                <p>
                    Powered by <a href="https://github.com/Yamiru/WP-GameServerlistMonR" target="_blank" style="color: #3b82f6; text-decoration: none;">MonR</a> 
                    - A project by <a href="https://yamiru.com" target="_blank" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Yamiru</a> 
                    | Version <?php echo GSLM_VERSION; ?>
                </p>
                <?php if (class_exists('GSLM_GameQueryLib') && GSLM_GameQueryLib::isGameQInstalled()): ?>
                <p style="font-size: 12px; margin-top: 10px; color: #6b7280;">
                    This plugin uses the <a href="https://github.com/Austinb/GameQ" target="_blank" style="color: #3b82f6; text-decoration: none;">GameQ PHP library</a> 
                    by Austin B., which is licensed under the GNU Lesser General Public License v3.0.
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let totalOnline = 0;
            let totalPlayers = 0;
            let totalChecked = 0;
            let totalServers = $('.gslm-status-check').length;
            
            // Check server statuses
            $('.gslm-status-check').each(function() {
                const $element = $(this);
                const serverId = $element.data('server-id');
                const $playersElement = $('.gslm-players-check[data-server-id="' + serverId + '"]');
                const $mapElement = $('.gslm-map-check[data-server-id="' + serverId + '"]');
                const $statusIcon = $('.server-status-icon[data-server-id="' + serverId + '"]');
                const $hostname = $('.server-hostname[data-server-id="' + serverId + '"]');
                const $gametype = $('.server-gametype[data-server-id="' + serverId + '"]');
                
                $.ajax({
                    url: gslm_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gslm_get_server_status',
                        nonce: gslm_admin.nonce,
                        server_id: serverId
                    },
                    success: function(response) {
                        totalChecked++;
                        
                        if (response.success && response.data.online) {
                            $element.html('<span style="color: #10b981; font-weight: 600;">✓ ONLINE</span>');
                            if ($statusIcon.length) {
                                $statusIcon.text('🟢');
                            }
                            
                            totalOnline++;
                            
                            // Update hostname from server query if available
                            if (response.data.gq_hostname && response.data.gq_hostname !== '') {
                                $hostname.text(response.data.gq_hostname);
                            } else if ($hostname.data('original') && $hostname.data('original') !== '') {
                                $hostname.text($hostname.data('original'));
                            }
                            
                            // Update game type from server query if available
                            if (response.data.gq_gametype && response.data.gq_gametype !== '') {
                                $gametype.text(response.data.gq_gametype);
                            }
                            
                            // Update players count with max players
                            if (response.data.gq_numplayers !== undefined && response.data.gq_maxplayers !== undefined) {
                                const players = response.data.gq_numplayers + '/' + response.data.gq_maxplayers;
                                $playersElement.html('<strong>' + players + '</strong>');
                                totalPlayers += parseInt(response.data.gq_numplayers);
                            } else {
                                $playersElement.html('0/0');
                            }
                            
                            // Update map
                            if (response.data.gq_mapname && response.data.gq_mapname !== '') {
                                $mapElement.html('<em>' + response.data.gq_mapname + '</em>');
                            } else {
                                $mapElement.html('—');
                            }
                        } else {
                            $element.html('<span style="color: #ef4444; font-weight: 600;">✗ OFFLINE</span>');
                            if ($statusIcon.length) {
                                $statusIcon.text('🔴');
                            }
                            $playersElement.html('<span style="color: #ef4444;">Offline</span>');
                            $mapElement.html('—');
                            
                            // Keep original name when offline
                            if ($hostname.data('original') && $hostname.data('original') !== '') {
                                $hostname.text($hostname.data('original'));
                            }
                            
                            // Keep original type when offline
                            if ($gametype.data('original')) {
                                $gametype.text($gametype.data('original'));
                            }
                        }
                        
                        // Update totals
                        $('#online-servers').text(totalOnline);
                        $('#total-players').text(totalPlayers);
                        
                        // Calculate uptime
                        if (totalChecked === totalServers) {
                            const uptime = totalServers > 0 ? Math.round((totalOnline / totalServers) * 100) : 0;
                            $('#uptime-percent').text(uptime + '%');
                        }
                    },
                    error: function() {
                        totalChecked++;
                        $element.html('<span style="color: #6b7280;">⚠ ERROR</span>');
                        if ($statusIcon.length) {
                            $statusIcon.text('⚪');
                        }
                        $playersElement.html('—');
                        $mapElement.html('—');
                    }
                });
            });
            
            // Install GameQ button
            $('#install-gameq-btn').on('click', function() {
                const $btn = $(this);
                const $spinner = $btn.next('.spinner');
                const $result = $('#gameq-install-result');
                
                $btn.prop('disabled', true);
                $spinner.show();
                $result.hide();
                
                $.ajax({
                    url: gslm_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gslm_install_gameq',
                        nonce: gslm_admin.nonce
                    },
                    success: function(response) {
                        $spinner.hide();
                        
                        if (response.success) {
                            $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').fadeIn();
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').fadeIn();
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        $spinner.hide();
                        $result.html('<div class="notice notice-error"><p>Installation failed. Please try manual installation.</p></div>').fadeIn();
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function serversPage() {
        $servers = GSLM_Database::getServers();
        ?>
        <div class="wrap gslm-admin-wrap">
            <h1>
                All Servers
                <a href="<?php echo admin_url('admin.php?page=gslm-add-server'); ?>" class="page-title-action">Add New</a>
            </h1>
            
            <?php if (empty($servers)): ?>
                <div class="notice notice-info">
                    <p>No servers found. <a href="<?php echo admin_url('admin.php?page=gslm-add-server'); ?>">Add your first server</a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 50px;">Icon</th>
                            <th>Server Name</th>
                            <th>Type</th>
                            <th>Address</th>
                            <th>Query Port</th>
                            <th>Status</th>
                            <th>Shortcode</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $server): 
                            $server_type = strtolower($server->server_type);
                            $is_discord = ($server_type === 'discord');
                        ?>
                        <tr>
                            <td><?php echo $server->id; ?></td>
                            <td>
                                <?php if (!empty($server->custom_icon_url)): ?>
                                    <img src="<?php echo esc_url($server->custom_icon_url); ?>" style="width: 24px; height: 24px;">
                                <?php elseif (!empty($server->game_icon)): ?>
                                    <span style="font-size: 24px;"><?php echo esc_html($server->game_icon); ?></span>
                                <?php else: ?>
                                    <span style="opacity: 0.3;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($server->server_name); ?></strong></td>
                            <td><?php echo esc_html($server->server_type); ?></td>
                            <td>
                                <?php 
                                if ($is_discord) {
                                    echo !empty($server->discord_invite) ? 'Discord' : 'Not set';
                                } else {
                                    echo esc_html($server->server_ip . ':' . $server->server_port);
                                }
                                ?>
                            </td>
                            <td><?php echo $server->query_port ? esc_html($server->query_port) : 'Same as game port'; ?></td>
                            <td class="gslm-status-check" data-server-id="<?php echo $server->id; ?>">Checking...</td>
                            <td><code>[game_server id="<?php echo $server->id; ?>"]</code></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=gslm-add-server&id=' . $server->id); ?>" class="button button-small">Edit</a>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="gslm_action" value="delete_server">
                                    <input type="hidden" name="server_id" value="<?php echo $server->id; ?>">
                                    <?php wp_nonce_field('gslm_admin_action', 'gslm_nonce'); ?>
                                    <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Delete this server?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft">
                        <p><strong>Shortcode to display all servers:</strong> <code>[monr_list]</code></p>
                        <p><strong>With parameters:</strong> <code>[monr_list type="minecraft" theme="dark" show_offline="yes"]</code></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function addServerPage() {
        $server = null;
        if (isset($_GET['id'])) {
            $server = GSLM_Database::getServer(intval($_GET['id']));
        }
        
        $is_edit = $server !== null;
        ?>
        <div class="wrap gslm-admin-wrap">
            <h1><?php echo $is_edit ? 'Edit Server' : 'Add New Server'; ?></h1>
            
            <form method="post" action="">
                <input type="hidden" name="gslm_action" value="save_server">
                <?php if ($is_edit): ?>
                <input type="hidden" name="server_id" value="<?php echo $server->id; ?>">
                <?php endif; ?>
                <?php wp_nonce_field('gslm_admin_action', 'gslm_nonce'); ?>
                
                <div class="gslm-form-section">
                    <h2>Server Information</h2>
                    
                    <div class="gslm-form-row">
                        <label for="server_name">Display Name (Optional)</label>
                        <input type="text" id="server_name" name="server_name" value="<?php echo $is_edit ? esc_attr($server->server_name) : ''; ?>">
                        <p class="description">Leave empty to use the server's actual hostname from query</p>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label for="server_type">Server Type / Game *</label>
                        <input type="text" id="server_type" name="server_type" value="<?php echo $is_edit ? esc_attr($server->server_type) : ''; ?>" required placeholder="e.g. minecraft, csgo, rust, discord">
                        <p class="description">
                            Supported types: minecraft, csgo, cs2, css, tf2, gmod, rust, ark, valheim, terraria, dayz, 
                            7daystodie, unturned, discord, teamspeak, mumble, fivem, samp, and more
                        </p>
                    </div>
                    
                    <div class="gslm-form-row" id="server-address-row">
                        <label for="server_ip">Server Address *</label>
                        <input type="text" id="server_ip" name="server_ip" value="<?php echo $is_edit ? esc_attr($server->server_ip) : ''; ?>" placeholder="IP address or domain name">
                        <p class="description">
                            Enter the server address:<br>
                            • <strong>IP Address:</strong> 192.168.1.100<br>
                            • <strong>Domain Name:</strong> play.example.com<br>
                            • <strong>For Discord:</strong> Leave empty or enter "discord"
                        </p>
                    </div>
                    
                    <div class="gslm-form-row" id="server-port-row">
                        <label for="server_port">Game Port</label>
                        <input type="number" id="server_port" name="server_port" value="<?php echo $is_edit ? esc_attr($server->server_port) : '25565'; ?>">
                        <p class="description">The port players use to connect (not needed for Discord)</p>
                    </div>
                    
                    <div class="gslm-form-row" id="query-port-row">
                        <label for="query_port">Query Port (Optional)</label>
                        <input type="number" id="query_port" name="query_port" value="<?php echo $is_edit ? esc_attr($server->query_port) : ''; ?>">
                        <p class="description">
                            Some games use a different port for status queries:<br>
                            • Rust: Game port + 400<br>
                            • ARK: Game port + 1<br>
                            • TeamSpeak: Usually 10011<br>
                            Leave empty to use the game port.
                        </p>
                    </div>
                    
                    <div class="gslm-form-row" id="discord-invite-row">
                        <label for="discord_invite">Discord Invite Link *</label>
                        <input type="text" id="discord_invite" name="discord_invite" value="<?php echo $is_edit ? esc_attr($server->discord_invite) : ''; ?>" placeholder="https://discord.gg/example or just the code">
                        <p class="description">
                            Enter Discord invite:<br>
                            • Full link: https://discord.gg/example<br>
                            • Just code: example
                        </p>
                    </div>
                </div>
                
                <div class="gslm-form-section">
                    <h2>Display Settings</h2>
                    
                    <div class="gslm-form-row">
                        <label for="game_icon">Icon (Emoji) - Optional</label>
                        <input type="text" id="game_icon" name="game_icon" value="<?php echo $is_edit ? esc_attr($server->game_icon) : ''; ?>" maxlength="10" placeholder="Leave empty for status icons">
                        <p class="description">Enter an emoji or leave empty for automatic status icons (🟢 online, 🔴 offline)</p>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label for="custom_icon_url">Custom Icon URL - Optional</label>
                        <input type="url" id="custom_icon_url" name="custom_icon_url" value="<?php echo $is_edit ? esc_url($server->custom_icon_url) : ''; ?>" placeholder="https://example.com/icon.png">
                        <p class="description">URL to icon image (overrides emoji)</p>
                        <?php if ($is_edit && !empty($server->custom_icon_url)): ?>
                            <p>Current: <img src="<?php echo esc_url($server->custom_icon_url); ?>" style="width: 24px; height: 24px; vertical-align: middle;"></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label for="theme_style">Theme Style</label>
                        <select id="theme_style" name="theme_style">
                            <option value="modern" <?php echo $is_edit && $server->theme_style == 'modern' ? 'selected' : ''; ?>>Modern Minimal</option>
                            <option value="dark" <?php echo $is_edit && $server->theme_style == 'dark' ? 'selected' : ''; ?>>Dark Futuristic</option>
                            <option value="light" <?php echo $is_edit && $server->theme_style == 'light' ? 'selected' : ''; ?>>Light Clean</option>
                            <option value="glass" <?php echo $is_edit && $server->theme_style == 'glass' ? 'selected' : ''; ?>>Glassmorphism</option>
                            <option value="neon" <?php echo $is_edit && $server->theme_style == 'neon' ? 'selected' : ''; ?>>Neon Cyber</option>
                        </select>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label>
                            <input type="checkbox" name="show_players" value="1" <?php echo !$is_edit || $server->show_players ? 'checked' : ''; ?>>
                            Show player count
                        </label>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label>
                            <input type="checkbox" name="show_map" value="1" <?php echo !$is_edit || $server->show_map ? 'checked' : ''; ?>>
                            Show current map (not for Discord)
                        </label>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label>
                            <input type="checkbox" name="show_connect" value="1" <?php echo !$is_edit || $server->show_connect ? 'checked' : ''; ?>>
                            Show connect/join button
                        </label>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label for="custom_connect_link">Custom Connect Link - Optional</label>
                        <input type="text" id="custom_connect_link" name="custom_connect_link" value="<?php echo $is_edit ? esc_attr($server->custom_connect_link) : ''; ?>" placeholder="steam://connect/ip:port">
                        <p class="description">Leave empty for automatic generation based on game type.</p>
                    </div>
                </div>
                
                <div class="gslm-form-section">
                    <h2>Auto Refresh Settings</h2>
                    
                    <div class="gslm-form-row">
                        <label>
                            <input type="checkbox" name="auto_refresh" value="1" <?php echo !$is_edit || $server->auto_refresh ? 'checked' : ''; ?>>
                            Enable auto-refresh
                        </label>
                        <p class="description">Automatically update server status</p>
                    </div>
                    
                    <div class="gslm-form-row">
                        <label for="refresh_interval">Refresh Interval (seconds)</label>
                        <input type="number" id="refresh_interval" name="refresh_interval" value="<?php echo $is_edit ? esc_attr($server->refresh_interval) : '60'; ?>" min="30" max="3600">
                        <p class="description">How often to check server status (30-3600 seconds)</p>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large"><?php echo $is_edit ? 'Update Server' : 'Add Server'; ?></button>
                    <a href="<?php echo admin_url('admin.php?page=gslm-servers'); ?>" class="button button-large">Cancel</a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Dynamic form based on server type
            function toggleFormFields() {
                const serverType = $('#server_type').val().toLowerCase();
                
                if (serverType === 'discord') {
                    $('#server-address-row').hide();
                    $('#server-port-row').hide();
                    $('#query-port-row').hide();
                    $('#discord-invite-row').show();
                    $('#server_ip').removeAttr('required');
                    $('#discord_invite').attr('required', 'required');
                } else {
                    $('#server-address-row').show();
                    $('#server-port-row').show();
                    $('#query-port-row').show();
                    $('#discord-invite-row').hide();
                    $('#server_ip').attr('required', 'required');
                    $('#discord_invite').removeAttr('required');
                }
            }
            
            $('#server_type').on('input change', toggleFormFields);
            toggleFormFields(); // Run on page load
            
            // Auto-detect port based on game type
            $('#server_type').on('change', function() {
                const type = $(this).val().toLowerCase();
                const currentPort = $('#server_port').val();
                
                // Only change if it's the default value
                if (currentPort == '25565' || currentPort == '') {
                    const defaultPorts = {
                        'minecraft': 25565,
                        'csgo': 27015,
                        'cs2': 27015,
                        'css': 27015,
                        'tf2': 27015,
                        'gmod': 27015,
                        'rust': 28015,
                        'ark': 7777,
                        'terraria': 7777,
                        'valheim': 2456,
                        'teamspeak': 9987,
                        'mumble': 64738,
                        'fivem': 30120,
                        'samp': 7777,
                        '7daystodie': 26900,
                        'unturned': 27015,
                        'dayz': 2302
                    };
                    
                    if (defaultPorts[type]) {
                        $('#server_port').val(defaultPorts[type]);
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    public function settingsPage() {
        ?>
        <div class="wrap gslm-admin-wrap">
            <h1>Settings</h1>
            
            <form method="post" action="">
                <input type="hidden" name="gslm_action" value="save_settings">
                <?php wp_nonce_field('gslm_admin_action', 'gslm_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Refresh Interval</th>
                        <td>
                            <input type="number" name="default_refresh_interval" value="<?php echo get_option('gslm_default_refresh_interval', 60); ?>" min="10" max="3600">
                            <p class="description">Default refresh interval in seconds for new servers (10-3600)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Cache</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_cache" value="1" <?php checked(get_option('gslm_enable_cache', true)); ?>>
                                Enable server status caching to reduce server load
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Cache Duration</th>
                        <td>
                            <input type="number" name="cache_duration" value="<?php echo get_option('gslm_cache_duration', 300); ?>" min="60" max="3600">
                            <p class="description">Cache duration in seconds (60-3600)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Default Theme</th>
                        <td>
                            <select name="default_theme">
                                <option value="modern" <?php selected(get_option('gslm_default_theme'), 'modern'); ?>>Modern</option>
                                <option value="dark" <?php selected(get_option('gslm_default_theme'), 'dark'); ?>>Dark</option>
                                <option value="light" <?php selected(get_option('gslm_default_theme'), 'light'); ?>>Light</option>
                                <option value="glass" <?php selected(get_option('gslm_default_theme'), 'glass'); ?>>Glass</option>
                                <option value="neon" <?php selected(get_option('gslm_default_theme'), 'neon'); ?>>Neon</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Statistics</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_statistics" value="1" <?php checked(get_option('gslm_enable_statistics', true)); ?>>
                                Track server statistics (player counts, uptime)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; opacity: 0.7;">
                <p>
                    Powered by <a href="https://github.com/Yamiru/WP-GameServerlistMonR" target="_blank" style="color: #3b82f6; text-decoration: none;">MonR</a> 
                    - A project by <a href="https://yamiru.com" target="_blank" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Yamiru</a> 
                    | Version <?php echo GSLM_VERSION; ?>
                </p>
                <?php if (class_exists('GSLM_GameQueryLib') && GSLM_GameQueryLib::isGameQInstalled()): ?>
                <p style="font-size: 12px; margin-top: 10px; color: #6b7280;">
                    This plugin uses the <a href="https://github.com/Austinb/GameQ" target="_blank" style="color: #3b82f6; text-decoration: none;">GameQ PHP library</a> 
                    by Austin B., which is licensed under the GNU Lesser General Public License v3.0.
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function toolsPage() {
        $gameq_status = GSLM_Helper::getGameQStatus();
        ?>
        <div class="wrap gslm-admin-wrap">
            <h1>Tools & Maintenance</h1>
            
            <div class="gslm-form-section">
                <h2>GameQuery Library Status</h2>
                <?php if ($gameq_status['installed']): ?>
                    <div class="notice notice-success inline">
                        <p><strong>✓ GameQ Installed</strong></p>
                        <p>The official GameQ library is installed and active. Enhanced server querying is enabled.</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p><strong>⚠ GameQ Not Installed</strong></p>
                        <p>Using built-in fallback queries. Install GameQ for better protocol support.</p>
                        <p>
                            <button id="install-gameq-tools" class="button button-primary">
                                Install GameQ Now
                            </button>
                            <span class="spinner" style="display: none; float: none;"></span>
                        </p>
                        <div id="gameq-tools-result" style="display: none; margin-top: 10px;"></div>
                    </div>
                <?php endif; ?>
                
                <h3>Supported Game Protocols</h3>
                <p>With GameQ installed, the following protocols are fully supported:</p>
                <ul style="columns: 3; column-gap: 30px;">
                    <li>• Minecraft Java & Bedrock</li>
                    <li>• Counter-Strike (All versions)</li>
                    <li>• Team Fortress 2</li>
                    <li>• Garry's Mod</li>
                    <li>• Rust</li>
                    <li>• ARK: Survival Evolved</li>
                    <li>• Valheim</li>
                    <li>• DayZ</li>
                    <li>• 7 Days to Die</li>
                    <li>• Unturned</li>
                    <li>• Left 4 Dead 1 & 2</li>
                    <li>• Terraria</li>
                    <li>• TeamSpeak 3</li>
                    <li>• Mumble</li>
                    <li>• Discord</li>
                    <li>• FiveM</li>
                    <li>• And many more...</li>
                </ul>
            </div>
            
            <div class="gslm-form-section">
                <h2>Database Management</h2>
                
                <p>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="gslm_action" value="reinstall_plugin">
                        <?php wp_nonce_field('gslm_admin_action', 'gslm_nonce'); ?>
                        <button type="submit" class="button button-primary" onclick="return confirm('This will delete all servers and reset the plugin. Continue?')">
                            Reinstall Database
                        </button>
                    </form>
                    <span class="description">Drops and recreates all database tables (removes all data)</span>
                </p>
                
                <p>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="gslm_action" value="clear_all_data">
                        <?php wp_nonce_field('gslm_admin_action', 'gslm_nonce'); ?>
                        <button type="submit" class="button" onclick="return confirm('This will delete ALL plugin data. Continue?')">
                            Clear All Data
                        </button>
                    </form>
                    <span class="description">Removes all servers and cached data</span>
                </p>
            </div>
            
            <div class="gslm-form-section">
                <h2>Database Information</h2>
                <?php
                $db_info = GSLM_Tools::getDatabaseInfo();
                ?>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Records</th>
                            <th>Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db_info as $table => $info): ?>
                        <tr>
                            <td><?php echo ucfirst($table); ?></td>
                            <td><?php echo $info['count']; ?></td>
                            <td><?php echo $info['size']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="gslm-form-section">
                <h2>Quick Debug Info</h2>
                <ul>
                    <li>Plugin Version: <?php echo GSLM_VERSION; ?></li>
                    <li>WordPress Version: <?php echo get_bloginfo('version'); ?></li>
                    <li>PHP Version: <?php echo phpversion(); ?></li>
                    <li>Memory Limit: <?php echo WP_MEMORY_LIMIT; ?></li>
                    <li>Debug Mode: <?php echo WP_DEBUG ? 'Enabled' : 'Disabled'; ?></li>
                    <li>GameQ Status: <?php echo $gameq_status['installed'] ? 'Installed' : 'Not Installed'; ?></li>
                </ul>
            </div>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; opacity: 0.7;">
                <p>
                    Powered by <a href="https://github.com/Yamiru/WP-GameServerlistMonR" target="_blank" style="color: #3b82f6; text-decoration: none;">MonR</a> 
                    - A project by <a href="https://yamiru.com" target="_blank" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Yamiru</a> 
                    | Version <?php echo GSLM_VERSION; ?>
                </p>
                <?php if (class_exists('GSLM_GameQueryLib') && GSLM_GameQueryLib::isGameQInstalled()): ?>
                <p style="font-size: 12px; margin-top: 10px; color: #6b7280;">
                    This plugin uses the <a href="https://github.com/Austinb/GameQ" target="_blank" style="color: #3b82f6; text-decoration: none;">GameQ PHP library</a> 
                    by Austin B., which is licensed under the GNU Lesser General Public License v3.0.
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#install-gameq-tools').on('click', function() {
                const $btn = $(this);
                const $spinner = $btn.next('.spinner');
                const $result = $('#gameq-tools-result');
                
                $btn.prop('disabled', true);
                $spinner.show();
                $result.hide();
                
                $.ajax({
                    url: gslm_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gslm_install_gameq',
                        nonce: gslm_admin.nonce
                    },
                    success: function(response) {
                        $spinner.hide();
                        
                        if (response.success) {
                            $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').fadeIn();
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').fadeIn();
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        $spinner.hide();
                        $result.html('<div class="notice notice-error"><p>Installation failed. Please try manual installation.</p></div>').fadeIn();
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // AJAX handler for GameQ installation
    public function ajaxInstallGameQ() {
        // Security check - verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gslm_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed - invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Additional security - check referer
        if (!check_admin_referer('gslm_admin_nonce', 'nonce')) {
            wp_send_json_error(array('message' => 'Security check failed - invalid referer'));
            return;
        }
        
        $result = GSLM_GameQueryLib::installGameQ();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    private function saveServer() {
        $server_type = strtolower(sanitize_text_field($_POST['server_type']));
        
        // Handle Discord servers specially
        if ($server_type === 'discord') {
            $server_ip = 'discord';
            $server_port = 0;
            $query_port = null;
        } else {
            $server_ip = sanitize_text_field($_POST['server_ip']);
            $server_port = intval($_POST['server_port']);
            $query_port = !empty($_POST['query_port']) ? intval($_POST['query_port']) : null;
        }
        
        $data = array(
            'server_name' => sanitize_text_field($_POST['server_name']),
            'server_type' => $server_type,
            'server_ip' => $server_ip,
            'server_port' => $server_port,
            'query_port' => $query_port,
            'game_icon' => sanitize_text_field($_POST['game_icon'] ?? ''),
            'custom_icon_url' => esc_url_raw($_POST['custom_icon_url'] ?? ''),
            'refresh_interval' => intval($_POST['refresh_interval'] ?? 60),
            'auto_refresh' => isset($_POST['auto_refresh']) ? 1 : 0,
            'theme_style' => sanitize_text_field($_POST['theme_style'] ?? 'modern'),
            'show_players' => isset($_POST['show_players']) ? 1 : 0,
            'show_map' => isset($_POST['show_map']) ? 1 : 0,
            'show_connect' => isset($_POST['show_connect']) ? 1 : 0,
            'custom_connect_link' => esc_url_raw($_POST['custom_connect_link'] ?? ''),
            'discord_invite' => sanitize_text_field($_POST['discord_invite'] ?? '')
        );
        
        if (isset($_POST['server_id'])) {
            $data['id'] = intval($_POST['server_id']);
        }
        
        $result = GSLM_Database::saveServer($data);
        
        if ($result !== false) {
            $this->addNotice('success', 'Server saved successfully!');
            wp_redirect(admin_url('admin.php?page=gslm-servers'));
            exit;
        } else {
            $this->addNotice('error', 'Failed to save server.');
        }
    }
    
    private function deleteServer() {
        $server_id = intval($_POST['server_id']);
        GSLM_Database::deleteServer($server_id);
        $this->addNotice('success', 'Server deleted successfully!');
        wp_redirect(admin_url('admin.php?page=gslm-servers'));
        exit;
    }
    
    private function saveSettings() {
        update_option('gslm_default_refresh_interval', intval($_POST['default_refresh_interval']));
        update_option('gslm_enable_cache', isset($_POST['enable_cache']) ? true : false);
        update_option('gslm_cache_duration', intval($_POST['cache_duration']));
        update_option('gslm_default_theme', sanitize_text_field($_POST['default_theme']));
        update_option('gslm_enable_statistics', isset($_POST['enable_statistics']) ? true : false);
        
        $this->addNotice('success', 'Settings saved successfully!');
        wp_redirect(admin_url('admin.php?page=gslm-settings'));
        exit;
    }
    
    private function reinstallPlugin() {
        $result = GSLM_Tools::reinstallPlugin();
        
        if ($result['success']) {
            $this->addNotice('success', $result['message']);
        } else {
            $this->addNotice('error', $result['message']);
        }
        
        wp_redirect(admin_url('admin.php?page=gslm-tools'));
        exit;
    }
    
    private function clearAllData() {
        global $wpdb;
        
        // Clear all servers
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . "gslm_servers");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . "gslm_cache");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . "gslm_statistics");
        
        $this->addNotice('success', 'All data cleared successfully!');
        wp_redirect(admin_url('admin.php?page=gslm-tools'));
        exit;
    }
    
    private function addNotice($type, $message) {
        set_transient('gslm_admin_notice', array(
            'type' => $type,
            'message' => $message
        ), 30);
    }
    
    public function displayNotices() {
        $notice = get_transient('gslm_admin_notice');
        
        if ($notice) {
            $class = 'notice notice-' . $notice['type'] . ' is-dismissible';
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($notice['message']));
            delete_transient('gslm_admin_notice');
        }
    }
}
