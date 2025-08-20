<?php
/**
 * Game Query Library for GameServerlistMonR
 * File: includes/class-gslm-gamequerylib.php
 * 
 * @package GameServerlistMonR
 * @version 1.0.0
 * @link https://github.com/Yamiru/WP-GameServerlistMonR
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_GameQueryLib {
    
    private static $gameq_loaded = false;
    private static $gameq_checked = false; // Prevent multiple checks
    
    /**
     * Check if GameQ is installed - with caching to prevent multiple checks
     */
    public static function isGameQInstalled() {
        // Return cached result if already checked
        if (self::$gameq_checked) {
            return self::$gameq_loaded;
        }
        
        self::$gameq_checked = true;
        
        if (self::loadGameQ()) {
            self::$gameq_loaded = class_exists('\\GameQ\\GameQ');
            return self::$gameq_loaded;
        }
        return false;
    }
    
    /**
     * Load GameQ Library
     */
    private static function loadGameQ() {
        if (self::$gameq_loaded) {
            return true;
        }
        
        $plugin_dir = GSLM_PLUGIN_DIR;
        
        // Check for GameQ directory first
        $gameq_dir = $plugin_dir . 'GameQ';
        if (!is_dir($gameq_dir)) {
            return false;
        }
        
        // Try to load autoloader
        $autoloader_file = $gameq_dir . '/Autoloader.php';
        if (file_exists($autoloader_file)) {
            require_once $autoloader_file;
            self::$gameq_loaded = true;
            return true;
        }
        
        // Try vendor autoload (Composer)
        if (file_exists($plugin_dir . 'vendor/autoload.php')) {
            require_once $plugin_dir . 'vendor/autoload.php';
            self::$gameq_loaded = true;
            return true;
        }
        
        return false;
    }
    
    /**
     * Install GameQ - simplified version
     */
    public static function installGameQ() {
        // Load installer class if needed
        $installer_file = GSLM_PLUGIN_DIR . 'includes/class-gslm-gameq-installer.php';
        if (!class_exists('GSLM_GameQ_Installer') && file_exists($installer_file)) {
            require_once $installer_file;
        }
        
        if (class_exists('GSLM_GameQ_Installer')) {
            return GSLM_GameQ_Installer::install();
        }
        
        // Fallback if installer class not found
        return array(
            'success' => false,
            'message' => 'GameQ installer not found. Please install manually.'
        );
    }
    
    /**
     * Main query method
     */
    public static function query($server) {
        // Validate input
        if (!is_object($server) || !isset($server->server_type)) {
            return self::getOfflineResponse('Invalid server object');
        }
        
        $server_type = strtolower($server->server_type);
        
        // Discord special case
        if ($server_type === 'discord') {
            return self::queryDiscord($server);
        }
        
        // Try GameQ v3 if available
        if (self::isGameQInstalled()) {
            try {
                $result = self::queryWithGameQv3($server);
                if ($result !== false) {
                    return $result;
                }
            } catch (Exception $e) {
                error_log('[GSLM] GameQ error: ' . $e->getMessage());
            }
        }
        
        // Fallback to built-in queries
        return self::queryBuiltIn($server);
    }
    
    /**
     * Query using GameQ v3 according to official documentation
     */
    private static function queryWithGameQv3($server) {
        try {
            // Make sure GameQ is loaded
            if (!class_exists('\\GameQ\\GameQ')) {
                return false;
            }
            
            // Create a new GameQ instance
            $GameQ = new \GameQ\GameQ();
            
            // Set options according to GameQ v3 documentation
            $GameQ->setOption('timeout', 5); // seconds
            $GameQ->setOption('debug', false);
            
            // Get the correct protocol
            $protocol = self::getGameQProtocol($server->server_type);
            
            // Prepare server array according to GameQ v3 format
            $servers = array(
                array(
                    'type' => $protocol,
                    'host' => $server->server_ip . ':' . $server->server_port,
                    'id' => 'server_' . ($server->id ?? 'temp')
                )
            );
            
            // Add query port if different from game port
            if (!empty($server->query_port) && $server->query_port != $server->server_port) {
                $servers[0]['options'] = array(
                    'query_port' => $server->query_port
                );
            }
            
            // Special handling for specific games
            if ($protocol === 'teamspeak3') {
                $servers[0]['options']['query_port'] = $server->query_port ?: 10011;
                $servers[0]['options']['port'] = $server->server_port ?: 9987;
            }
            
            // Add servers to GameQ
            $GameQ->addServers($servers);
            
            // Process and get results
            $results = $GameQ->process();
            
            // Check if we have results
            $server_key = 'server_' . ($server->id ?? 'temp');
            if (!isset($results[$server_key])) {
                return false;
            }
            
            // Format the result
            return self::formatGameQv3Result($results[$server_key], $server);
            
        } catch (Exception $e) {
            error_log('[GSLM] GameQ v3 exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format GameQ v3 result to our standard format
     */
    private static function formatGameQv3Result($result, $server) {
        // Check if server is online
        $is_online = isset($result['gq_online']) && $result['gq_online'] === true;
        
        if (!$is_online) {
            return self::getOfflineResponse('Server offline', $server->server_type);
        }
        
        // Build standardized response
        $response = array(
            'online' => true,
            'gq_online' => true,
            'gq_hostname' => '',
            'gq_gametype' => '',
            'gq_numplayers' => 0,
            'gq_maxplayers' => 0,
            'gq_mapname' => '',
            'server_type' => $server->server_type
        );
        
        // Map hostname
        if (isset($result['gq_hostname'])) {
            $response['gq_hostname'] = $result['gq_hostname'];
        } elseif (isset($result['hostname'])) {
            $response['gq_hostname'] = $result['hostname'];
        } elseif (isset($result['name'])) {
            $response['gq_hostname'] = $result['name'];
        } else {
            $response['gq_hostname'] = $server->server_name ?: 'Server';
        }
        
        // Map game type
        if (isset($result['gq_gametype'])) {
            $response['gq_gametype'] = $result['gq_gametype'];
        } elseif (isset($result['game'])) {
            $response['gq_gametype'] = $result['game'];
        } elseif (isset($result['gametype'])) {
            $response['gq_gametype'] = $result['gametype'];
        } else {
            $response['gq_gametype'] = ucfirst($server->server_type);
        }
        
        // Map player count
        if (isset($result['gq_numplayers'])) {
            $response['gq_numplayers'] = intval($result['gq_numplayers']);
        } elseif (isset($result['num_players'])) {
            $response['gq_numplayers'] = intval($result['num_players']);
        } elseif (isset($result['players']) && is_numeric($result['players'])) {
            $response['gq_numplayers'] = intval($result['players']);
        } elseif (isset($result['players']) && is_array($result['players'])) {
            $response['gq_numplayers'] = count($result['players']);
        }
        
        // Map max players
        if (isset($result['gq_maxplayers'])) {
            $response['gq_maxplayers'] = intval($result['gq_maxplayers']);
        } elseif (isset($result['max_players'])) {
            $response['gq_maxplayers'] = intval($result['max_players']);
        } elseif (isset($result['maxplayers'])) {
            $response['gq_maxplayers'] = intval($result['maxplayers']);
        }
        
        // Map current map
        if (isset($result['gq_mapname'])) {
            $response['gq_mapname'] = $result['gq_mapname'];
        } elseif (isset($result['map'])) {
            $response['gq_mapname'] = $result['map'];
        } elseif (isset($result['mapname'])) {
            $response['gq_mapname'] = $result['mapname'];
        }
        
        // Add version if available
        if (isset($result['version'])) {
            $response['version'] = $result['version'];
        }
        
        // Add raw data for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $response['raw_gameq'] = $result;
        }
        
        return $response;
    }
    
    /**
     * Get GameQ protocol name from our server type
     */
    private static function getGameQProtocol($type) {
        $type = strtolower($type);
        
        // Protocol mapping according to GameQ v3
        $protocols = array(
            // Minecraft variants
            'minecraft' => 'minecraft',
            'minecraft java' => 'minecraft',
            'mc' => 'minecraft',
            'minecraft bedrock' => 'minecraftpe',
            'mcpe' => 'minecraftpe',
            'bedrock' => 'minecraftpe',
            
            // Quake series
            'quake' => 'quake',
            'quake1' => 'quake',
            'quake2' => 'quake2',
            'quake3' => 'quake3',
            'q3' => 'quake3',
            'quake3arena' => 'quake3',
            'q3a' => 'quake3',
            'quake4' => 'quake4',
            'q4' => 'quake4',
            'quakelive' => 'quakelive',
            'ql' => 'quakelive',
            
            // Call of Duty series
            'cod' => 'cod',
            'cod2' => 'cod2',
            'cod4' => 'cod4',
            'codmw' => 'cod4',
            'codmw2' => 'codmw2',
            'codmw3' => 'codmw3',
            'codbo' => 'codbo',
            'codbo2' => 'codbo2',
            'codbo3' => 'codbo3',
            'codaw' => 'codaw',
            'codiw' => 'codiw',
            'codww2' => 'codww2',
            
            // Battlefield series
            'bf' => 'bf1942',
            'bf1942' => 'bf1942',
            'bfv' => 'bfv',
            'bf2' => 'bf2',
            'bf2142' => 'bf2142',
            'bfbc2' => 'bfbc2',
            'bf3' => 'bf3',
            'bf4' => 'bf4',
            'bf1' => 'bf1',
            'bfh' => 'bfh',
            
            // Source Engine games
            'source' => 'source',
            'csgo' => 'csgo',
            'cs2' => 'cs2',
            'css' => 'css',
            'cs16' => 'cs16',
            'cs' => 'cs16',
            'cscz' => 'cscz',
            'tf2' => 'tf2',
            'tf' => 'tf2',
            'gmod' => 'gmod',
            'garrysmod' => 'gmod',
            'left4dead' => 'left4dead',
            'l4d' => 'left4dead',
            'left4dead2' => 'left4dead2',
            'l4d2' => 'left4dead2',
            'hl' => 'halflife',
            'halflife' => 'halflife',
            'hl2' => 'hl2dm',
            'hl2dm' => 'hl2dm',
            'dod' => 'dod',
            'dods' => 'dods',
            
            // Unreal Engine games
            'unreal' => 'unreal',
            'unreal2' => 'unreal2',
            'ut' => 'ut',
            'ut2003' => 'ut2003',
            'ut2004' => 'ut2004',
            'ut3' => 'ut3',
            
            // Survival games
            'rust' => 'rust',
            'ark' => 'arkse',
            'arkse' => 'arkse',
            'atlas' => 'atlas',
            'valheim' => 'valheim',
            'vrising' => 'vrising',
            'scum' => 'scum',
            'dayz' => 'dayz',
            'dayzmod' => 'dayzmod',
            '7daystodie' => '7d2d',
            '7d2d' => '7d2d',
            'unturned' => 'unturned',
            'zomboid' => 'zomboid',
            'projectzomboid' => 'zomboid',
            'conanexiles' => 'conanexiles',
            'ce' => 'conanexiles',
            
            // Voice servers
            'teamspeak' => 'teamspeak3',
            'teamspeak3' => 'teamspeak3',
            'ts3' => 'teamspeak3',
            'ts' => 'teamspeak3',
            'mumble' => 'mumble',
            'ventrilo' => 'ventrilo',
            'vent' => 'ventrilo',
            
            // GTA variants
            'fivem' => 'fivem',
            'gtav' => 'fivem',
            'samp' => 'samp',
            'sampvoice' => 'sampvoice',
            'mta' => 'mta',
            'mtasa' => 'mta',
            'ragemp' => 'ragemp',
            'rage' => 'ragemp',
            'redm' => 'redm',
        );
        
        // Return protocol or default to 'source' for unknown games
        return isset($protocols[$type]) ? $protocols[$type] : 'source';
    }
    
    /**
     * Built-in fallback queries when GameQ is not available
     */
    private static function queryBuiltIn($server) {
        $type = strtolower($server->server_type);
        $ip = $server->server_ip;
        $port = $server->server_port;
        
        // Resolve domain to IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($ip);
            if ($resolved !== $ip) {
                $ip = $resolved;
            }
        }
        
        switch ($type) {
            case 'minecraft':
            case 'minecraft java':
            case 'mc':
                return self::queryMinecraftJava($ip, $port);
                
            case 'minecraft bedrock':
            case 'mcpe':
            case 'bedrock':
                return self::queryMinecraftBedrock($ip, $port);
                
            case 'quake':
            case 'quake3':
            case 'q3':
            case 'quake3arena':
            case 'q3a':
            case 'quakelive':
                $query_port = $server->query_port ?: $port;
                return self::queryQuake3($ip, $query_port);
                
            case 'csgo':
            case 'cs2':
            case 'css':
            case 'tf2':
            case 'gmod':
            case 'left4dead':
            case 'left4dead2':
                $query_port = $server->query_port ?: $port;
                return self::querySourceEngine($ip, $query_port, $type);
                
            case 'rust':
                $query_port = $server->query_port ?: ($port + 400);
                return self::querySourceEngine($ip, $query_port, 'rust');
                
            case 'teamspeak':
            case 'teamspeak3':
            case 'ts3':
                $query_port = $server->query_port ?: 10011;
                return self::queryTeamSpeak3($ip, $query_port);
                
            default:
                return self::queryGenericTCP($ip, $port, $type);
        }
    }
    
    /**
     * Query Discord server via API
     */
    private static function queryDiscord($server) {
        $invite = $server->discord_invite ?? '';
        
        if (empty($invite)) {
            return self::getOfflineResponse('No Discord invite', 'discord');
        }
        
        // Extract invite code from various formats
        if (preg_match('/(?:discord\.gg\/|discord\.com\/invite\/)([a-zA-Z0-9\-_]+)/i', $invite, $matches)) {
            $invite = $matches[1];
        } elseif (!preg_match('/^[a-zA-Z0-9\-_]+$/', $invite)) {
            return self::getOfflineResponse('Invalid Discord invite format', 'discord');
        }
        
        // Query Discord API
        $api_url = 'https://discord.com/api/v10/invites/' . $invite . '?with_counts=true&with_expiration=true';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; GameServerMonitor/2.7; +https://github.com/Yamiru/WP-GameServerlistMonR)'
            ),
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return self::getOfflineResponse($response->get_error_message(), 'discord');
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return self::getOfflineResponse('Discord API error: HTTP ' . $code, 'discord');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['guild'])) {
            return self::getOfflineResponse('Invalid or expired Discord invite', 'discord');
        }
        
        return array(
            'online' => true,
            'gq_online' => true,
            'gq_hostname' => $data['guild']['name'],
            'gq_gametype' => 'Discord',
            'gq_numplayers' => isset($data['approximate_presence_count']) ? intval($data['approximate_presence_count']) : 0,
            'gq_maxplayers' => isset($data['approximate_member_count']) ? intval($data['approximate_member_count']) : 0,
            'gq_mapname' => '',
            'discord_invite' => 'https://discord.gg/' . $data['code'],
            'server_type' => 'discord'
        );
    }
    
    // Rest of the built-in query methods remain the same...
    // (queryMinecraftJava, queryMinecraftBedrock, querySourceEngine, etc.)
    
    /**
     * Get offline response
     */
    private static function getOfflineResponse($error = '', $type = '') {
        return array(
            'online' => false,
            'gq_online' => false,
            'gq_hostname' => '',
            'gq_gametype' => ucfirst($type),
            'gq_numplayers' => 0,
            'gq_maxplayers' => 0,
            'gq_mapname' => '',
            'error' => $error,
            'server_type' => $type
        );
    }
    
    /**
     * Generic TCP port check
     */
    private static function queryGenericTCP($ip, $port, $type) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
        
        if ($fp) {
            fclose($fp);
            return array(
                'online' => true,
                'gq_online' => true,
                'gq_hostname' => 'Server',
                'gq_gametype' => ucfirst($type),
                'gq_numplayers' => 0,
                'gq_maxplayers' => 0,
                'gq_mapname' => '',
                'server_type' => $type,
                'note' => 'Basic port check - install GameQ for detailed info'
            );
        }
        
        return self::getOfflineResponse('Port ' . $port . ' is closed', $type);
    }
}