<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Application Configuration
 */

// Prevent direct script execution if accessed outside expected workflow (optional safeguard)
if (!defined('OPTICAL_CMS')) {
    define('OPTICAL_CMS', true);
}

// Application Metadata
define('APP_NAME', 'VisionCare Optical CMS');
define('APP_SHORT_NAME', 'Optical CMS');
define('APP_VERSION', '1.0.0');

// Path Definitions
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('UPLOAD_PATH', ROOT_PATH . '/uploads/avatars');

// Base URL Auto-Detection
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Calculate web root relative to document root
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $currentDir = str_replace('\\', '/', ROOT_PATH);
    $relativeDir = trim(str_replace($docRoot, '', $currentDir), '/');
    
    $baseUrl = $protocol . $host . ($relativeDir !== '' ? '/' . $relativeDir . '/' : '/');
    define('BASE_URL', $baseUrl);
}

define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOAD_URL', BASE_URL . 'uploads/avatars/');

// Timezone Configuration
date_default_timezone_set('UTC');

// Secure Session Initialization
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 60 * 60 * 24; // 24 hours
        
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        
        $cookieParams = [
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        session_set_cookie_params($cookieParams);
        session_start();
    }
}

/**
 * Check if the application has completed installation and is locked
 *
 * @return bool
 */
function isInstalled(): bool {
    $lockFile = CONFIG_PATH . '/installed.php';
    if (!file_exists($lockFile)) {
        return false;
    }
    $lockData = @include $lockFile;
    return is_array($lockData) && !empty($lockData['installed']);
}

// Auto-redirect to installer if application is not yet installed (for HTTP web requests)
if (!defined('IN_INSTALLER') && (php_sapi_name() !== 'cli')) {
    if (!isInstalled()) {
        header('Location: ' . BASE_URL . 'install/');
        exit;
    }
}
