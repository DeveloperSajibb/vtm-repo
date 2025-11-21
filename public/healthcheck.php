<?php
header('Content-Type: application/json');

// Create directories if they don't exist
$dirs = ['../storage', '../storage/sessions', '../storage/logs'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'checks' => []
];

// Basic PHP check
$health['checks']['php'] = ['status' => 'ok', 'message' => 'PHP is running'];

// Directory check with auto-create
foreach ($dirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        $health['checks']["directory_$dir"] = [
            'status' => 'ok',
            'message' => "Directory exists and is writable"
        ];
    } else {
        // Try to fix permissions
        chmod($dir, 0777);
        
        if (is_dir($dir) && is_writable($dir)) {
            $health['checks']["directory_$dir"] = [
                'status' => 'ok', 
                'message' => "Directory fixed and is now writable"
            ];
        } else {
            $health['status'] = 'unhealthy';
            $health['checks']["directory_$dir"] = [
                'status' => 'error',
                'message' => "Directory cannot be created or made writable"
            ];
        }
    }
}

http_response_code($health['status'] === 'healthy' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);