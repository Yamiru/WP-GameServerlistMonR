<?php
/**
 * Enhanced GameQ Installer for GameServerlistMonR
 * 
 * @package GameServerlistMonR
 * @version 1.0.0
 * @link https://github.com/Yamiru/WP-GameServerlistMonR
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSLM_GameQ_Installer {
    
    /**
     * Install GameQ library
     */
    public static function install() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return array(
                'success' => false,
                'message' => __('Insufficient permissions', 'game-server-list-monitor')
            );
        }
        
        // Check if already installed
        if (self::isInstalled()) {
            return array(
                'success' => true,
                'message' => __('GameQ is already installed', 'game-server-list-monitor')
            );
        }
        
        $plugin_dir = GSLM_PLUGIN_DIR;
        
        // Enable WordPress Filesystem
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        // Try to install from GitHub master branch
        $result = self::installFromGitHub($plugin_dir);
        if ($result['success']) {
            return $result;
        }
        
        return array(
            'success' => false,
            'message' => __('Installation failed. Please install manually by downloading from https://github.com/Austinb/GameQ/archive/master.zip', 'game-server-list-monitor')
        );
    }
    
    /**
     * Install from GitHub master branch (latest version)
     */
    private static function installFromGitHub($plugin_dir) {
        try {
            // Use only master branch - latest version
            $url = 'https://github.com/Austinb/GameQ/archive/master.zip';
            
            if (!function_exists('download_url')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            
            // Download with WordPress function
            $temp_file = download_url($url, 300); // 5 minute timeout
            
            if (is_wp_error($temp_file)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to download GameQ: ' . $temp_file->get_error_message()
                );
            }
            
            if (!file_exists($temp_file) || filesize($temp_file) < 1000) {
                @unlink($temp_file);
                return array(
                    'success' => false,
                    'message' => 'Downloaded file is invalid or too small'
                );
            }
            
            // Create temp directory
            $temp_dir = $plugin_dir . 'temp_gameq_' . uniqid();
            if (!wp_mkdir_p($temp_dir)) {
                @unlink($temp_file);
                return array(
                    'success' => false,
                    'message' => 'Cannot create temp directory'
                );
            }
            
            // Extract ZIP
            WP_Filesystem();
            $unzip_result = unzip_file($temp_file, $temp_dir);
            @unlink($temp_file);
            
            if (is_wp_error($unzip_result)) {
                self::recursiveDelete($temp_dir);
                return array(
                    'success' => false,
                    'message' => 'Failed to extract ZIP: ' . $unzip_result->get_error_message()
                );
            }
            
            // Find the extracted GameQ-master directory
            $extracted_dir = $temp_dir . '/GameQ-master';
            if (!is_dir($extracted_dir)) {
                // Try to find any GameQ directory
                $dirs = glob($temp_dir . '/GameQ*', GLOB_ONLYDIR);
                if (!empty($dirs)) {
                    $extracted_dir = $dirs[0];
                } else {
                    self::recursiveDelete($temp_dir);
                    return array(
                        'success' => false,
                        'message' => 'GameQ directory not found in archive'
                    );
                }
            }
            
            // Look for src/GameQ directory
            $source_dir = null;
            if (is_dir($extracted_dir . '/src/GameQ')) {
                $source_dir = $extracted_dir . '/src/GameQ';
            } elseif (is_dir($extracted_dir . '/GameQ')) {
                $source_dir = $extracted_dir . '/GameQ';
            }
            
            if (!$source_dir || !is_dir($source_dir)) {
                self::recursiveDelete($temp_dir);
                return array(
                    'success' => false,
                    'message' => 'GameQ source directory not found in archive'
                );
            }
            
            // Destination directory
            $destination = $plugin_dir . 'GameQ';
            
            // Remove existing GameQ directory if exists
            if (is_dir($destination)) {
                self::recursiveDelete($destination);
            }
            
            // Copy GameQ to destination
            if (!self::recursiveCopy($source_dir, $destination)) {
                self::recursiveDelete($temp_dir);
                return array(
                    'success' => false,
                    'message' => 'Failed to copy GameQ files'
                );
            }
            
            // Create proper autoloader for GameQ v3
            self::createProperAutoloader($destination);
            
            // Cleanup temp directory
            self::recursiveDelete($temp_dir);
            
            // Verify installation
            if (self::isInstalled()) {
                return array(
                    'success' => true,
                    'message' => __('GameQ installed successfully from latest version!', 'game-server-list-monitor')
                );
            }
            
            return array(
                'success' => false,
                'message' => 'Installation completed but verification failed'
            );
            
        } catch (Exception $e) {
            if (isset($temp_dir) && is_dir($temp_dir)) {
                self::recursiveDelete($temp_dir);
            }
            if (isset($temp_file) && file_exists($temp_file)) {
                @unlink($temp_file);
            }
            
            return array(
                'success' => false,
                'message' => 'Exception during installation: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create proper autoloader for GameQ v3
     */
    private static function createProperAutoloader($gameq_dir) {
        // First check if original Autoloader.php exists from GameQ
        if (file_exists($gameq_dir . '/Autoloader.php')) {
            // Original autoloader exists, just use it
            return;
        }
        
        // Create our own autoloader
        $autoloader_content = '<?php
/**
 * GameQ v3 Autoloader
 * Auto-generated by Game Server List Monitor
 */

namespace GameQ;

/**
 * Handles auto-loading of classes
 */
class Autoloader
{
    /**
     * Path to the GameQ library
     */
    protected $path;

    /**
     * Constructor
     */
    public function __construct($path = null)
    {
        if ($path === null) {
            $this->path = __DIR__;
        } else {
            $this->path = rtrim($path, "/");
        }
    }

    /**
     * Register the autoloader
     */
    public function register()
    {
        spl_autoload_register([$this, "loadClass"]);
    }

    /**
     * Load a class
     */
    public function loadClass($class)
    {
        // Check if this is a GameQ class
        if (strpos($class, "GameQ\\") !== 0) {
            return;
        }

        // Build the file path
        $file = $this->path . "/" . str_replace("\\", "/", substr($class, 6)) . ".php";

        // Load the file if it exists
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

// Auto-register the autoloader
$autoloader = new Autoloader();
$autoloader->register();
';
        
        file_put_contents($gameq_dir . '/Autoloader.php', $autoloader_content);
    }
    
    /**
     * Check if GameQ is installed
     */
    public static function isInstalled() {
        $plugin_dir = GSLM_PLUGIN_DIR;
        $gameq_dir = $plugin_dir . 'GameQ';
        
        // Check if directory exists
        if (!is_dir($gameq_dir)) {
            return false;
        }
        
        // Check for main GameQ file
        if (!file_exists($gameq_dir . '/GameQ.php')) {
            return false;
        }
        
        // Check if autoloader exists, create if missing
        if (!file_exists($gameq_dir . '/Autoloader.php')) {
            self::createProperAutoloader($gameq_dir);
        }
        
        // Try to load GameQ
        if (!class_exists('\\GameQ\\GameQ')) {
            $autoloader_file = $gameq_dir . '/Autoloader.php';
            if (file_exists($autoloader_file)) {
                require_once $autoloader_file;
            }
        }
        
        // Final check - try to instantiate GameQ
        try {
            if (class_exists('\\GameQ\\GameQ')) {
                $test = new \GameQ\GameQ();
                return true;
            }
        } catch (Exception $e) {
            // GameQ exists but might have issues
            error_log('[GSLM] GameQ test failed: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Recursive copy directory
     */
    private static function recursiveCopy($src, $dst) {
        if (!is_dir($src)) {
            return false;
        }
        
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                if (is_dir($src . '/' . $file)) {
                    self::recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        
        return true;
    }
    
    /**
     * Recursive delete directory
     */
    private static function recursiveDelete($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($dir);
    }
}