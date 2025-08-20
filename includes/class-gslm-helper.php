<?php
/**
 * Helper Functions Class for GameServerlistMonR
 * File: includes/class-gslm-helper.php
 * 
 * @package GameServerListMonitor
 * @version 1.0.0
 * @link https://github.com/Yamiru/WP-GameServerlistMonR
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_Helper {
    
    /**
     * Query server status - MAIN METHOD
     */
    public static function queryServer($server) {
        // Validate server object
        if (!is_object($server) || !isset($server->server_type)) {
            return self::getOfflineResponse('Invalid server configuration');
        }
        
        $server_type = strtolower($server->server_type);
        
        // Special handling for Discord
        if ($server_type === 'discord') {
            return self::queryDiscord($server);
        }
        
        // Try GameQ first if available
        if (class_exists('GSLM_GameQueryLib') && GSLM_GameQueryLib::isGameQInstalled()) {
            try {
                $result = GSLM_GameQueryLib::query($server);
                if ($result && isset($result['online']) && $result['online']) {
                    return self::formatResponse($result, $server);
                }
            } catch (Exception $e) {
                error_log('[GSLM] GameQ query failed: ' . $e->getMessage());
            }
        }
        
        // Fallback to built-in queries
        return self::queryBuiltIn($server);
    }
    
    /**
     * Query Discord server via API
     */
    private static function queryDiscord($server) {
        $invite_code = $server->discord_invite ?? '';
        
        if (empty($invite_code)) {
            return self::getOfflineResponse('No Discord invite provided', 'discord', $server);
        }
        
        // Extract invite code from URL if needed
        if (preg_match('/(?:discord\.gg\/|discord\.com\/invite\/)([a-zA-Z0-9\-_]+)/i', $invite_code, $matches)) {
            $invite_code = $matches[1];
        } elseif (!preg_match('/^[a-zA-Z0-9\-_]+$/', $invite_code)) {
            return self::getOfflineResponse('Invalid Discord invite format', 'discord', $server);
        }
        
        // Query Discord API using WordPress HTTP API
        $api_url = 'https://discord.com/api/v10/invites/' . $invite_code . '?with_counts=true&with_expiration=true';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; GameServerMonitor/2.7; +https://github.com/Yamiru/WP-GameServerlistMonR)'
            ),
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return self::getOfflineResponse($response->get_error_message(), 'discord', $server);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return self::getOfflineResponse('Discord API error: HTTP ' . $response_code, 'discord', $server);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['guild'])) {
            return self::getOfflineResponse('Invalid or expired Discord invite', 'discord', $server);
        }
        
        // Return successful response
        return array(
            'online' => true,
            'gq_online' => true,
            'gq_hostname' => $data['guild']['name'],
            'gq_gametype' => 'Discord',
            'gq_numplayers' => isset($data['approximate_presence_count']) ? intval($data['approximate_presence_count']) : 0,
            'gq_maxplayers' => isset($data['approximate_member_count']) ? intval($data['approximate_member_count']) : 0,
            'gq_mapname' => '',
            'map' => '',
            'hostname' => $data['guild']['name'],
            'players' => isset($data['approximate_presence_count']) ? intval($data['approximate_presence_count']) : 0,
            'max_players' => isset($data['approximate_member_count']) ? intval($data['approximate_member_count']) : 0,
            'server_type' => 'discord',
            'discord_invite' => 'https://discord.gg/' . $data['code'],
            'icon' => isset($data['guild']['icon']) ? 
                'https://cdn.discordapp.com/icons/' . $data['guild']['id'] . '/' . $data['guild']['icon'] . '.png' : null
        );
    }
    
    /**
     * Built-in query methods
     */
    private static function queryBuiltIn($server) {
        $server_type = strtolower($server->server_type);
        $ip = $server->server_ip;
        $port = $server->server_port;
        $query_port = $server->query_port ?: $port;
        
        // Resolve domain to IP if needed
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $resolved_ip = gethostbyname($ip);
            if ($resolved_ip === $ip) {
                // Could not resolve domain, but try anyway
                $resolved_ip = $ip;
            }
            $ip = $resolved_ip;
        }
        
        switch ($server_type) {
            case 'minecraft':
            case 'minecraft java':
            case 'mc':
                return self::queryMinecraft($ip, $port, $server);
                
            case 'minecraft bedrock':
            case 'mcpe':
                return self::queryMinecraftBedrock($ip, $port, $server);
                
            case 'csgo':
            case 'cs2':
            case 'css':
            case 'tf2':
            case 'gmod':
            case 'garrysmod':
            case 'left4dead':
            case 'left4dead2':
                return self::querySourceEngine($ip, $query_port, $server_type, $server);
                
            case 'rust':
                // Rust uses game port + 400 for query
                $rust_query_port = $query_port ?: ($port + 400);
                return self::querySourceEngine($ip, $rust_query_port, 'rust', $server);
                
            case 'teamspeak':
            case 'teamspeak3':
            case 'ts3':
                $ts_query_port = $query_port ?: 10011;
                return self::queryTeamSpeak3($ip, $ts_query_port, $server);
                
            default:
                // Try generic TCP check
                return self::queryGeneric($ip, $port, $server_type, $server);
        }
    }
    
    /**
     * Minecraft Java Edition query
     */
    private static function queryMinecraft($ip, $port, $server) {
        $timeout = 3;
        
        // Try server list ping first (newer protocol)
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        
        if (!$socket) {
            return self::getOfflineResponse("Connection failed: $errstr", 'minecraft', $server);
        }
        
        stream_set_timeout($socket, $timeout);
        
        try {
            // Handshake packet
            $packet = "\x00"; // Packet ID
            $packet .= "\x04"; // Protocol version (1.7.10+)
            $packet .= chr(strlen($ip)) . $ip; // Server address
            $packet .= pack('n', $port); // Server port
            $packet .= "\x01"; // Next state (status)
            
            $packet = chr(strlen($packet)) . $packet;
            fwrite($socket, $packet);
            
            // Status request
            fwrite($socket, "\x01\x00");
            
            // Read response
            $length = self::readVarInt($socket);
            if ($length < 1) {
                fclose($socket);
                return self::getOfflineResponse('Invalid response from server', 'minecraft', $server);
            }
            
            $packet_id = fread($socket, 1);
            $json_length = self::readVarInt($socket);
            
            if ($json_length < 1 || $json_length > 32768) {
                fclose($socket);
                return self::getOfflineResponse('Invalid JSON length', 'minecraft', $server);
            }
            
            $json = fread($socket, $json_length);
            fclose($socket);
            
            $data = json_decode($json, true);
            
            if (!$data) {
                return self::getOfflineResponse('Failed to parse server response', 'minecraft', $server);
            }
            
            // Parse MOTD
            $hostname = 'Minecraft Server';
            if (isset($data['description'])) {
                if (is_string($data['description'])) {
                    $hostname = $data['description'];
                } elseif (isset($data['description']['text'])) {
                    $hostname = $data['description']['text'];
                } elseif (isset($data['description']['extra'])) {
                    $texts = array();
                    foreach ($data['description']['extra'] as $part) {
                        if (isset($part['text'])) {
                            $texts[] = $part['text'];
                        }
                    }
                    $hostname = implode('', $texts);
                }
            }
            
            // Clean Minecraft formatting codes
            $hostname = preg_replace('/§[0-9a-fk-or]/i', '', $hostname);
            $hostname = trim($hostname);
            
            return array(
                'online' => true,
                'gq_online' => true,
                'gq_hostname' => $hostname ?: $server->server_name,
                'gq_gametype' => 'Minecraft',
                'gq_numplayers' => isset($data['players']['online']) ? intval($data['players']['online']) : 0,
                'gq_maxplayers' => isset($data['players']['max']) ? intval($data['players']['max']) : 20,
                'gq_mapname' => 'world',
                'map' => 'world',
                'hostname' => $hostname ?: $server->server_name,
                'players' => isset($data['players']['online']) ? intval($data['players']['online']) : 0,
                'max_players' => isset($data['players']['max']) ? intval($data['players']['max']) : 20,
                'version' => isset($data['version']['name']) ? $data['version']['name'] : 'Unknown',
                'server_type' => 'minecraft'
            );
            
        } catch (Exception $e) {
            @fclose($socket);
            return self::getOfflineResponse('Query error: ' . $e->getMessage(), 'minecraft', $server);
        }
    }
    
    /**
     * Minecraft Bedrock Edition query
     */
    private static function queryMinecraftBedrock($ip, $port, $server) {
        if (!function_exists('socket_create')) {
            // Try alternative method using fsockopen with UDP
            return self::queryGeneric($ip, $port, 'minecraft bedrock', $server);
        }
        
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        
        if (!$socket) {
            return self::getOfflineResponse('Failed to create socket', 'minecraft bedrock', $server);
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 3, "usec" => 0));
        
        // Unconnected ping packet for Bedrock
        $packet = "\x01"; // ID
        $packet .= pack('J', time()); // Time
        $packet .= "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78"; // Magic
        $packet .= pack('J', 0); // Client GUID
        
        @socket_sendto($socket, $packet, strlen($packet), 0, $ip, $port);
        
        $buffer = '';
        $from = '';
        $port_from = 0;
        @socket_recvfrom($socket, $buffer, 4096, 0, $from, $port_from);
        socket_close($socket);
        
        if (strlen($buffer) > 35) {
            // Parse response
            $info = substr($buffer, 35);
            $data = explode(';', $info);
            
            if (count($data) >= 6) {
                return array(
                    'online' => true,
                    'gq_online' => true,
                    'gq_hostname' => isset($data[1]) ? $data[1] : ($server->server_name ?: 'Bedrock Server'),
                    'gq_gametype' => 'Minecraft Bedrock',
                    'gq_numplayers' => isset($data[4]) ? intval($data[4]) : 0,
                    'gq_maxplayers' => isset($data[5]) ? intval($data[5]) : 20,
                    'gq_mapname' => isset($data[7]) ? $data[7] : 'world',
                    'map' => isset($data[7]) ? $data[7] : 'world',
                    'hostname' => isset($data[1]) ? $data[1] : ($server->server_name ?: 'Bedrock Server'),
                    'players' => isset($data[4]) ? intval($data[4]) : 0,
                    'max_players' => isset($data[5]) ? intval($data[5]) : 20,
                    'version' => isset($data[3]) ? $data[3] : 'Unknown',
                    'server_type' => 'minecraft bedrock'
                );
            }
        }
        
        return self::getOfflineResponse('No response from server', 'minecraft bedrock', $server);
    }
    
    /**
     * Source Engine query (CS:GO, CS2, TF2, GMod, etc.)
     */
    private static function querySourceEngine($ip, $port, $game_type, $server) {
        $fp = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 3);
        
        if (!$fp) {
            return self::getOfflineResponse("Connection failed: $errstr", $game_type, $server);
        }
        
        stream_set_timeout($fp, 3);
        
        // A2S_INFO query
        $query = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
        fwrite($fp, $query);
        
        $response = fread($fp, 4096);
        fclose($fp);
        
        if (strlen($response) < 5) {
            return self::getOfflineResponse('No response from server', $game_type, $server);
        }
        
        // Check for valid response
        if (substr($response, 0, 4) !== "\xFF\xFF\xFF\xFF") {
            return self::getOfflineResponse('Invalid response header', $game_type, $server);
        }
        
        // Parse A2S_INFO response
        if (substr($response, 4, 1) === "\x49") {
            $data = substr($response, 5);
            
            // Protocol version
            $protocol = ord($data[0]);
            $data = substr($data, 1);
            
            // Server name
            $pos = strpos($data, "\x00");
            if ($pos === false) {
                return self::getOfflineResponse('Invalid server data', $game_type, $server);
            }
            $hostname = substr($data, 0, $pos);
            $data = substr($data, $pos + 1);
            
            // Map name
            $pos = strpos($data, "\x00");
            if ($pos === false) {
                return self::getOfflineResponse('Invalid map data', $game_type, $server);
            }
            $map = substr($data, 0, $pos);
            $data = substr($data, $pos + 1);
            
            // Folder
            $pos = strpos($data, "\x00");
            if ($pos === false) {
                return self::getOfflineResponse('Invalid folder data', $game_type, $server);
            }
            $data = substr($data, $pos + 1);
            
            // Game
            $pos = strpos($data, "\x00");
            if ($pos === false) {
                return self::getOfflineResponse('Invalid game data', $game_type, $server);
            }
            $game = substr($data, 0, $pos);
            $data = substr($data, $pos + 1);
            
            // Skip app ID (2 bytes)
            if (strlen($data) < 4) {
                return self::getOfflineResponse('Invalid player data', $game_type, $server);
            }
            $data = substr($data, 2);
            
            // Players and max players
            $players = ord($data[0]);
            $max_players = ord($data[1]);
            
            return array(
                'online' => true,
                'gq_online' => true,
                'gq_hostname' => $hostname ?: $server->server_name,
                'gq_gametype' => $game ?: $game_type,
                'gq_numplayers' => $players,
                'gq_maxplayers' => $max_players,
                'gq_mapname' => $map,
                'map' => $map,
                'hostname' => $hostname ?: $server->server_name,
                'players' => $players,
                'max_players' => $max_players,
                'server_type' => $game_type
            );
        }
        
        return self::getOfflineResponse('Unsupported response type', $game_type, $server);
    }
    
    /**
     * TeamSpeak 3 query
     */
    private static function queryTeamSpeak3($ip, $port, $server) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
        
        if (!$fp) {
            return self::getOfflineResponse("Connection failed: $errstr", 'teamspeak3', $server);
        }
        
        stream_set_timeout($fp, 3);
        
        // Skip welcome message
        fread($fp, 4096);
        
        // Send query commands
        fwrite($fp, "use sid=1\r\n");
        fread($fp, 4096);
        
        fwrite($fp, "serverinfo\r\n");
        $response = fread($fp, 4096);
        fclose($fp);
        
        // Parse response
        if (strpos($response, 'error id=0') !== false) {
            preg_match('/virtualserver_name=([^\s]+)/', $response, $name);
            preg_match('/virtualserver_clientsonline=(\d+)/', $response, $players);
            preg_match('/virtualserver_maxclients=(\d+)/', $response, $max);
            
            $hostname = isset($name[1]) ? str_replace('\s', ' ', $name[1]) : ($server->server_name ?: 'TeamSpeak Server');
            
            return array(
                'online' => true,
                'gq_online' => true,
                'gq_hostname' => $hostname,
                'gq_gametype' => 'TeamSpeak 3',
                'gq_numplayers' => isset($players[1]) ? intval($players[1]) : 0,
                'gq_maxplayers' => isset($max[1]) ? intval($max[1]) : 32,
                'gq_mapname' => '',
                'map' => '',
                'hostname' => $hostname,
                'players' => isset($players[1]) ? intval($players[1]) : 0,
                'max_players' => isset($max[1]) ? intval($max[1]) : 32,
                'server_type' => 'teamspeak3'
            );
        }
        
        return self::getOfflineResponse('Query failed', 'teamspeak3', $server);
    }
    
    /**
     * Generic TCP port check
     */
    private static function queryGeneric($ip, $port, $type, $server) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
        
        if ($fp) {
            fclose($fp);
            return array(
                'online' => true,
                'gq_online' => true,
                'gq_hostname' => $server->server_name ?: 'Server',
                'gq_gametype' => ucfirst($type),
                'gq_numplayers' => 0,
                'gq_maxplayers' => 0,
                'gq_mapname' => '',
                'map' => '',
                'hostname' => $server->server_name ?: 'Server',
                'players' => 0,
                'max_players' => 0,
                'server_type' => $type,
                'note' => 'Port is open (basic check)'
            );
        }
        
        return self::getOfflineResponse("Port $port is closed", $type, $server);
    }
    
    /**
     * Get offline response
     */
    private static function getOfflineResponse($error = '', $type = '', $server = null) {
        return array(
            'online' => false,
            'gq_online' => false,
            'gq_hostname' => ($server && $server->server_name) ? $server->server_name : '',
            'gq_gametype' => $type ? ucfirst($type) : '',
            'gq_numplayers' => 0,
            'gq_maxplayers' => 0,
            'gq_mapname' => '',
            'map' => '',
            'hostname' => ($server && $server->server_name) ? $server->server_name : '',
            'players' => 0,
            'max_players' => 0,
            'error' => $error,
            'server_type' => $type
        );
    }
    
    /**
     * Format response to ensure all fields exist
     */
    private static function formatResponse($data, $server) {
        $response = array(
            'online' => isset($data['online']) ? $data['online'] : false,
            'gq_online' => isset($data['gq_online']) ? $data['gq_online'] : false,
            'gq_hostname' => '',
            'gq_gametype' => '',
            'gq_numplayers' => 0,
            'gq_maxplayers' => 0,
            'gq_mapname' => '',
            'map' => '',
            'hostname' => '',
            'players' => 0,
            'max_players' => 0,
            'server_type' => $server->server_type
        );
        
        // Copy all data
        foreach ($data as $key => $value) {
            $response[$key] = $value;
        }
        
        // Ensure hostname
        if (empty($response['gq_hostname']) && !empty($response['hostname'])) {
            $response['gq_hostname'] = $response['hostname'];
        } elseif (!empty($response['gq_hostname']) && empty($response['hostname'])) {
            $response['hostname'] = $response['gq_hostname'];
        } elseif (empty($response['gq_hostname']) && empty($response['hostname'])) {
            $response['gq_hostname'] = $response['hostname'] = $server->server_name ?: 'Server';
        }
        
        // Ensure player counts
        if (isset($data['gq_numplayers'])) {
            $response['players'] = $data['gq_numplayers'];
        } elseif (isset($data['players'])) {
            $response['gq_numplayers'] = $data['players'];
        }
        
        if (isset($data['gq_maxplayers'])) {
            $response['max_players'] = $data['gq_maxplayers'];
        } elseif (isset($data['max_players'])) {
            $response['gq_maxplayers'] = $data['max_players'];
        }
        
        // Ensure map
        if (isset($data['gq_mapname']) && !empty($data['gq_mapname'])) {
            $response['map'] = $data['gq_mapname'];
        } elseif (isset($data['map']) && !empty($data['map'])) {
            $response['gq_mapname'] = $data['map'];
        }
        
        return $response;
    }
    
    /**
     * Read VarInt for Minecraft protocol
     */
    private static function readVarInt($socket) {
        $value = 0;
        $position = 0;
        
        while (true) {
            $byte = fread($socket, 1);
            if ($byte === false || strlen($byte) == 0) {
                return 0;
            }
            
            $byte = ord($byte);
            $value |= ($byte & 0x7F) << ($position * 7);
            
            if (($byte & 0x80) == 0) {
                break;
            }
            
            $position++;
            if ($position >= 5) {
                return 0;
            }
        }
        
        return $value;
    }
    
    /**
     * Get connect link for server
     */
    public static function getConnectLink($server) {
        // Use custom link if provided
        if (!empty($server->custom_connect_link)) {
            return $server->custom_connect_link;
        }
        
        $server_type = strtolower($server->server_type);
        
        // Discord
        if ($server_type === 'discord') {
            if (!empty($server->discord_invite)) {
                $invite = $server->discord_invite;
                if (strpos($invite, 'http') !== 0) {
                    if (strpos($invite, 'discord.gg/') === false) {
                        return 'https://discord.gg/' . $invite;
                    }
                    return 'https://' . $invite;
                }
                return $invite;
            }
            return '#';
        }
        
        $ip = $server->server_ip;
        $port = $server->server_port;
        
        // Protocol mappings
        $protocols = array(
            'minecraft' => 'minecraft://{ip}:{port}',
            'minecraft java' => 'minecraft://{ip}:{port}',
            'minecraft bedrock' => 'minecraft://{ip}:{port}',
            'teamspeak' => 'ts3server://{ip}?port={port}',
            'teamspeak3' => 'ts3server://{ip}?port={port}',
            'mumble' => 'mumble://{ip}:{port}',
            'fivem' => 'fivem://connect/{ip}:{port}',
            'samp' => 'samp://{ip}:{port}'
        );
        
        if (isset($protocols[$server_type])) {
            return str_replace(
                array('{ip}', '{port}'),
                array($ip, $port),
                $protocols[$server_type]
            );
        }
        
        // Default to Steam protocol
        return 'steam://connect/' . $ip . ':' . $port;
    }
    
    /**
     * Get server type display name
     */
    public static function getServerTypeDisplay($type) {
        $display_names = array(
            'minecraft' => 'Minecraft',
            'minecraft java' => 'Minecraft Java',
            'minecraft bedrock' => 'Minecraft Bedrock',
            'csgo' => 'CS:GO',
            'cs2' => 'Counter-Strike 2',
            'css' => 'CS:Source',
            'tf2' => 'Team Fortress 2',
            'gmod' => "Garry's Mod",
            'rust' => 'Rust',
            'ark' => 'ARK',
            'valheim' => 'Valheim',
            'discord' => 'Discord',
            'teamspeak' => 'TeamSpeak 3',
            'teamspeak3' => 'TeamSpeak 3',
            'ts3' => 'TeamSpeak 3',
            'mumble' => 'Mumble',
            'fivem' => 'FiveM',
            'redm' => 'RedM',
            'samp' => 'SA-MP',
            'mta' => 'MTA:SA',
            'ragemp' => 'RAGE MP'
        );
        
        $type_lower = strtolower($type);
        return isset($display_names[$type_lower]) ? $display_names[$type_lower] : ucfirst($type);
    }
    
    /**
     * Get GameQ status
     */
    public static function getGameQStatus() {
        $installed = false;
        $message = '';
        
        if (class_exists('GSLM_GameQueryLib')) {
            $installed = GSLM_GameQueryLib::isGameQInstalled();
        }
        
        if ($installed) {
            $message = 'GameQ installed - Enhanced querying active';
        } else {
            $message = 'Using built-in fallback queries';
        }
        
        return array(
            'installed' => $installed,
            'message' => $message
        );
    }
}