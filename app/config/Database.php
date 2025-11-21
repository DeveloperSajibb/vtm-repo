<?php

/**
 * Database Configuration and Connection Class
 * 
 * Provides PDO database connection and basic CRUD operations
 * for the VTM Option application.
 */

namespace App\Config;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    
    private string $host;
    private string $database;
    private string $username;
    private string $password;
    private string $charset;
    private string $type; // 'mysql' or 'pgsql'
    private ?int $port = null;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        // Determine database type (default to mysql for backward compatibility)
        $this->type = strtolower($_ENV['DB_TYPE'] ?? getenv('DB_TYPE') ?: 'mysql');
        
        // Check for Railway's DATABASE_URL first (PostgreSQL format)
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        
        if ($databaseUrl && $this->type === 'pgsql') {
            // Parse Railway DATABASE_URL: postgresql://user:password@host:port/database
            $this->parseDatabaseUrl($databaseUrl);
        } else {
            // Load from individual environment variables
            $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
            $this->database = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'vtmoption';
            $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
            $this->password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
            $this->charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';
            
            // Get port if specified
            $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
            if ($port) {
                $this->port = (int)$port;
            }
        }
    }
    
    /**
     * Parse Railway DATABASE_URL
     * Format: postgresql://user:password@host:port/database
     */
    private function parseDatabaseUrl(string $url): void
    {
        $parsed = parse_url($url);
        
        if ($parsed === false) {
            throw new Exception("Invalid DATABASE_URL format");
        }
        
        $this->username = $parsed['user'] ?? 'postgres';
        $this->password = $parsed['pass'] ?? '';
        $this->host = $parsed['host'] ?? 'localhost';
        $this->port = isset($parsed['port']) ? (int)$parsed['port'] : ($this->type === 'pgsql' ? 5432 : 3306);
        $this->database = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'vtmoption';
        $this->charset = 'utf8'; // PostgreSQL uses utf8
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            // Build DSN based on database type
            if ($this->type === 'pgsql') {
                // PostgreSQL DSN
                $port = $this->port ?? 5432;
                $dsn = sprintf(
                    "pgsql:host=%s;port=%d;dbname=%s",
                    $this->host,
                    $port,
                    $this->database
                );
            } else {
                // MySQL DSN (default)
                $port = $this->port ?? 3306;
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $this->host,
                    $port,
                    $this->database,
                    $this->charset
                );
            }
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get database type
     */
    public function getType(): string
    {
        return $this->type;
    }
    
    /**
     * Close database connection
     */
    public function close(): void
    {
        $this->connection = null;
    }
    
    /**
     * Execute a query and return all results
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a query and return single row
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a query and return single value
     */
    public function queryValue(string $sql, array $params = []): mixed
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute an INSERT, UPDATE, or DELETE query
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Execute failed: " . $e->getMessage());
        }
    }
    
    /**
     * Insert a record and return the last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ":$field", $fields);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($data);
            return (int) $this->getConnection()->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Insert failed: " . $e->getMessage());
        }
    }
    
    /**
     * Update records
     */
    public function update(string $table, array $data, array $where, array $whereParams = []): int
    {
        $fields = array_keys($data);
        $setClause = implode(', ', array_map(fn($field) => "$field = :$field", $fields));
        
        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :where_$field", array_keys($where)));
        
        $sql = "UPDATE $table SET $setClause WHERE $whereClause";
        
        // Merge data and where params with prefix
        $params = $data;
        foreach ($where as $key => $value) {
            $params["where_$key"] = $value;
        }
        $params = array_merge($params, $whereParams);
        
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Update failed: " . $e->getMessage());
        }
    }
    
    /**
     * Delete records
     */
    public function delete(string $table, array $where, array $whereParams = []): int
    {
        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($where)));
        $sql = "DELETE FROM $table WHERE $whereClause";
        
        $params = array_merge($where, $whereParams);
        
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Delete failed: " . $e->getMessage());
        }
    }
    
    /**
     * Find a record by ID
     */
    public function findById(string $table, int $id): ?array
    {
        $sql = "SELECT * FROM $table WHERE id = :id LIMIT 1";
        return $this->queryOne($sql, ['id' => $id]);
    }
    
    /**
     * Find records by conditions
     */
    public function find(string $table, array $conditions = [], array $options = []): array
    {
        $sql = "SELECT * FROM $table";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($conditions)));
            $sql .= " WHERE $whereClause";
            $params = $conditions;
        }
        
        // Add ORDER BY
        if (isset($options['orderBy'])) {
            $sql .= " ORDER BY " . $options['orderBy'];
        }
        
        // Add LIMIT
        if (isset($options['limit'])) {
            $sql .= " LIMIT " . (int) $options['limit'];
            
            if (isset($options['offset'])) {
                $sql .= " OFFSET " . (int) $options['offset'];
            }
        }
        
        return $this->query($sql, $params);
    }
    
    /**
     * Count records
     */
    public function count(string $table, array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM $table";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($conditions)));
            $sql .= " WHERE $whereClause";
            $params = $conditions;
        }
        
        return (int) $this->queryValue($sql, $params);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->getConnection()->lastInsertId();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

