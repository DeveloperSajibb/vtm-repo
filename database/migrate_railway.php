<?php

/**
 * Railway Database Migration Runner
 * 
 * This script automatically detects the database type and runs the appropriate migration.
 * 
 * Usage:
 *   php database/migrate_railway.php
 * 
 * Or via Railway CLI:
 *   railway run php database/migrate_railway.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;

echo "========================================\n";
echo "Railway Database Migration Runner\n";
echo "========================================\n\n";

try {
    // Get database instance to determine type
    $db = Database::getInstance();
    $dbType = $db->getType();
    
    echo "Database Type: " . strtoupper($dbType) . "\n";
    echo "Connecting to database...\n";
    
    // Test connection
    $db->getConnection();
    echo "✓ Database connection successful\n\n";
    
    // Determine migration file
    $migrationFile = null;
    if ($dbType === 'pgsql') {
        $migrationFile = __DIR__ . '/migrations/001_initial_schema_postgresql.sql';
        echo "Using PostgreSQL migration: 001_initial_schema_postgresql.sql\n";
    } else {
        $migrationFile = __DIR__ . '/migrations/001_initial_schema.sql';
        echo "Using MySQL migration: 001_initial_schema.sql\n";
    }
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    echo "Reading migration file...\n";
    $sql = file_get_contents($migrationFile);
    
    if (empty($sql)) {
        throw new Exception("Migration file is empty");
    }
    
    echo "Executing migration...\n";
    
    // Split SQL into individual statements
    // Remove comments and empty lines
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Split by semicolon, but preserve function/procedure definitions
    $statements = [];
    $currentStatement = '';
    $inFunction = false;
    $delimiter = ';';
    
    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Check for DELIMITER command (MySQL)
        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            $delimiter = $matches[1];
            continue;
        }
        
        // Check for function/procedure start (PostgreSQL)
        if (preg_match('/^\s*(CREATE\s+(OR\s+REPLACE\s+)?(FUNCTION|PROCEDURE|TRIGGER))/i', $trimmed)) {
            $inFunction = true;
        }
        
        $currentStatement .= $line . "\n";
        
        // Check for end of statement
        if ($inFunction) {
            // PostgreSQL functions end with $$ or ;
            if (preg_match('/\$\$[\s;]*$/i', $trimmed) || 
                (preg_match('/END\s*;?\s*$/i', $trimmed) && !preg_match('/BEGIN/i', $currentStatement))) {
                $inFunction = false;
            }
        }
        
        // Check if statement ends with delimiter
        if (!$inFunction && preg_match('/' . preg_quote($delimiter, '/') . '\s*$/', $trimmed)) {
            $statement = trim($currentStatement);
            if (!empty($statement)) {
                $statements[] = $statement;
            }
            $currentStatement = '';
            $delimiter = ';'; // Reset delimiter
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    // Execute statements
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        if (empty($statement)) {
            continue;
        }
        
        try {
            // Skip CREATE DATABASE and USE statements
            if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $statement)) {
                echo "  [SKIP] " . substr($statement, 0, 50) . "...\n";
                continue;
            }
            
            $db->getConnection()->exec($statement);
            $executed++;
            
            // Show progress for major operations
            if (preg_match('/CREATE\s+(TABLE|VIEW|FUNCTION|PROCEDURE|TRIGGER|INDEX)/i', $statement, $matches)) {
                $objectType = strtolower($matches[1]);
                if (preg_match('/(?:TABLE|VIEW|FUNCTION|PROCEDURE|TRIGGER|INDEX)\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\s\(]+)/i', $statement, $nameMatches)) {
                    $objectName = $nameMatches[1];
                    echo "  [OK] Created $objectType: $objectName\n";
                }
            }
        } catch (PDOException $e) {
            // Some errors are expected (e.g., IF NOT EXISTS)
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'duplicate') !== false) {
                echo "  [SKIP] Already exists\n";
                continue;
            }
            
            $errors[] = [
                'statement' => substr($statement, 0, 100),
                'error' => $e->getMessage()
            ];
            echo "  [ERROR] " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n========================================\n";
    echo "Migration Summary\n";
    echo "========================================\n";
    echo "Statements executed: $executed\n";
    
    if (!empty($errors)) {
        echo "Errors encountered: " . count($errors) . "\n";
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - " . $error['error'] . "\n";
        }
    } else {
        echo "✓ Migration completed successfully!\n";
    }
    
    echo "\n";
    
    // Verify tables were created
    echo "Verifying tables...\n";
    $tables = ['users', 'trades', 'signals', 'settings', 'trading_sessions'];
    $foundTables = [];
    
    foreach ($tables as $table) {
        try {
            $result = $db->queryValue("SELECT COUNT(*) FROM $table");
            $foundTables[] = $table;
            echo "  ✓ Table '$table' exists\n";
        } catch (Exception $e) {
            echo "  ✗ Table '$table' not found\n";
        }
    }
    
    if (count($foundTables) === count($tables)) {
        echo "\n✓ All required tables exist!\n";
    } else {
        echo "\n⚠ Some tables are missing. Please check the migration.\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n========================================\n";
echo "Migration Complete!\n";
echo "========================================\n";

