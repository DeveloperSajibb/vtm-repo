<?php

/**
 * Health Check Endpoint for Railway
 * 
 * This endpoint is used by Railway to check if the application is running
 * and healthy. It performs basic checks on the application and database.
 */

// Disable error display for health checks
error_reporting(0);
ini_set('display_errors', 0);

// Set response headers
header('Content-Type: application/json');

// Health check status
$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// Check 1: PHP is running
$health['checks']['php'] = [
    'status' => 'ok',
    'version' => PHP_VERSION
];

// Check 2: Database connection
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/app/bootstrap.php';
    
    use App\Config\Database;
    
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    // Simple query to test connection
    $db->queryValue("SELECT 1");
    
    $health['checks']['database'] = [
        'status' => 'ok',
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}

// Check 3: Required directories exist
$requiredDirs = [
    'storage/sessions' => __DIR__ . '/storage/sessions',
    'public' => __DIR__ . '/public'
];

foreach ($requiredDirs as $name => $path) {
    if (is_dir($path) && is_writable($path)) {
        $health['checks']["directory_$name"] = [
            'status' => 'ok',
            'message' => "Directory $name exists and is writable"
        ];
    } else {
        $health['status'] = 'unhealthy';
        $health['checks']["directory_$name"] = [
            'status' => 'error',
            'message' => "Directory $name missing or not writable"
        ];
    }
}

// Check 4: Environment variables
$requiredEnvVars = ['APP_ENV', 'DB_HOST', 'DB_NAME'];
$missingVars = [];

foreach ($requiredEnvVars as $var) {
    if (empty($_ENV[$var] ?? getenv($var))) {
        $missingVars[] = $var;
    }
}

if (empty($missingVars)) {
    $health['checks']['environment'] = [
        'status' => 'ok',
        'message' => 'Required environment variables are set'
    ];
} else {
    $health['status'] = 'unhealthy';
    $health['checks']['environment'] = [
        'status' => 'error',
        'message' => 'Missing environment variables: ' . implode(', ', $missingVars)
    ];
}

// Set HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

// Return JSON response
echo json_encode($health, JSON_PRETTY_PRINT);

