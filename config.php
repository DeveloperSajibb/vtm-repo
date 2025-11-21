<?php

/**
 * Configuration File
 *
 * Centralises runtime configuration for both traditional hosting
 * and containerised (Docker) deployments. Values are loaded from
 * environment variables when available with sensible defaults for
 * local development.
 */

// ============================================================================
// ENVIRONMENT HELPER
// ============================================================================

if (!function_exists('env_value')) {
    /**
     * Retrieve environment variable with fallback support
     */
    function env_value(string $key, $default = null)
    {
        $value = getenv($key);

        if ($value === false || $value === null || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }

        return $value;
    }
}

// ============================================================================
// ENVIRONMENT CONFIGURATION
// ============================================================================

// Application environment (development or production)
define('APP_ENV', strtolower((string)env_value('APP_ENV', 'development')) === 'production' ? 'production' : 'development');

// Application URL (used for generating links)
// Railway provides RAILWAY_STATIC_URL or we can construct from PORT
$railwayUrl = env_value('RAILWAY_STATIC_URL');
$port = env_value('PORT', '8080');
if ($railwayUrl) {
    define('APP_URL', rtrim($railwayUrl, '/'));
} elseif ($port && $port !== '8080') {
    // For Railway, construct URL from PORT if available
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', "$protocol://$host");
} else {
    define('APP_URL', env_value('APP_URL', 'http://localhost:8080'));
}

// Application timezone
define('APP_TIMEZONE', env_value('APP_TIMEZONE', 'UTC'));

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

// Database type (mysql or pgsql) - defaults to mysql for backward compatibility
define('DB_TYPE', strtolower(env_value('DB_TYPE', 'mysql')));

// Database host
define('DB_HOST', env_value('DB_HOST', 'localhost'));

// Database port (default based on DB_TYPE)
$defaultPort = DB_TYPE === 'pgsql' ? '5432' : '3306';
define('DB_PORT', env_value('DB_PORT', $defaultPort));

// Database name
define('DB_NAME', env_value('DB_NAME', 'vtm'));

// Database username
define('DB_USER', env_value('DB_USER', 'root'));

// Database password
define('DB_PASS', env_value('DB_PASS', ''));

// Database charset
define('DB_CHARSET', env_value('DB_CHARSET', 'utf8mb4'));

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// Encryption key for API tokens (64-character hex string)
// Generated using: bin2hex(random_bytes(32))
// IMPORTANT: Keep this key secure and never commit it to version control
// To generate a new key, run: php -r "echo bin2hex(random_bytes(32));"
define('ENCRYPTION_KEY', env_value('ENCRYPTION_KEY', '7f3a9b2c8d4e1f6a5b9c2d7e3f8a1b4c6d9e2f5a8b1c4d7e0f3a6b9c2d5e8f1a4'));

// Session configuration
define('SESSION_LIFETIME', (int)env_value('SESSION_LIFETIME', 86400));

// ============================================================================
// DERIV API CONFIGURATION
// ============================================================================

// Deriv App ID (default is 105326)
define('DERIV_APP_ID', env_value('DERIV_APP_ID', '105326'));

// Deriv WebSocket host
// Correct hostname: ws.derivws.com (Deriv WebSocket) or wss.binaryws.com (Binary.com WebSocket)
define('DERIV_WS_HOST', env_value('DERIV_WS_HOST', 'ws.derivws.com'));

// ============================================================================
// ERROR HANDLING
// ============================================================================

// Error reporting (set to 0 in production)
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// Log errors
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log');

// ============================================================================
// PATH CONFIGURATION
// ============================================================================

// Base path (usually the directory where this file is located)
define('BASE_PATH', __DIR__);

// Public path (where public files are served from)
define('PUBLIC_PATH', BASE_PATH . '/public');

// App path
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

// Vendor path (Composer dependencies)
define('VENDOR_PATH', BASE_PATH . '/vendor');

// ============================================================================
// SET ENVIRONMENT VARIABLES FOR DOTENV COMPATIBILITY
// ============================================================================

// Set environment variables that dotenv would normally load
$_ENV['APP_ENV'] = APP_ENV;
$_ENV['APP_URL'] = APP_URL;
$_ENV['APP_TIMEZONE'] = APP_TIMEZONE;
$_ENV['DB_TYPE'] = DB_TYPE;
$_ENV['DB_HOST'] = DB_HOST;
$_ENV['DB_PORT'] = DB_PORT;
$_ENV['DB_NAME'] = DB_NAME;
$_ENV['DB_USER'] = DB_USER;
$_ENV['DB_PASS'] = DB_PASS;
$_ENV['DB_CHARSET'] = DB_CHARSET;
$_ENV['ENCRYPTION_KEY'] = ENCRYPTION_KEY;
$_ENV['DERIV_APP_ID'] = DERIV_APP_ID;
$_ENV['DERIV_WS_HOST'] = DERIV_WS_HOST;
$_ENV['SESSION_LIFETIME'] = SESSION_LIFETIME;

// ============================================================================
// TIMEZONE SETTING
// ============================================================================

date_default_timezone_set(APP_TIMEZONE);

// ============================================================================
// AUTOLOADER
// ============================================================================

// Load pure PHP autoloader (no Composer needed)
if (file_exists(__DIR__ . '/app/autoload.php')) {
    require_once __DIR__ . '/app/autoload.php';
} else {
    // Don't use die() as it outputs HTML - log error instead
    error_log('CRITICAL: Autoloader not found at: ' . __DIR__ . '/app/autoload.php');
    // If we're in an API context, try to return JSON
    if (php_sapi_name() !== 'cli' && strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error: Autoloader not found']);
        exit;
    }
    die('Autoloader not found. Please check file structure.');
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get configuration value
 */
function config(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $default;
    
    // Convert string booleans to actual booleans
    if ($value === 'true') return true;
    if ($value === 'false') return false;
    
    return $value;
}

/**
 * Check if application is in production
 */
function isProduction(): bool
{
    return APP_ENV === 'production';
}

/**
 * Check if application is in development
 */
function isDevelopment(): bool
{
    return APP_ENV === 'development';
}

