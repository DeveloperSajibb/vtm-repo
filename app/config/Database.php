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
        // Determine database type (default mysql)
        $this->type = strtolower($_ENV['DB_TYPE'] ?? getenv('DB_TYPE') ?: 'mysql');

        /**
         * ======================================================
         *  RAILWAY MYSQL DETECTION (OFFICIAL VARIABLES)
         * ======================================================
         */
        if (getenv('MYSQLHOST')) {

            $this->type     = 'mysql';
            $this->host     = getenv('MYSQLHOST');
            $this->database = getenv('MYSQLDATABASE');
            $this->username = getenv('MYSQLUSER');
            $this->password = getenv('MYSQLPASSWORD');
            $this->port     = (int)(getenv('MYSQLPORT') ?: 3306);
            $this->charset  = 'utf8mb4';

            return; // Railway config takes priority
        }

        /**
         * ======================================================
         *  RAILWAY POSTGRES (DATABASE_URL)
         * ======================================================
         */
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

        if ($databaseUrl && $this->type === 'pgsql') {
            $this->parseDatabaseUrl($databaseUrl);
            return;
        }

        /**
         * ======================================================
         *  FALLBACK TO LOCAL .ENV VARIABLES
         * ======================================================
         */
        $this->host     = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $this->database = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'vtmoption';
        $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $this->password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
        $this->charset  = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
        if ($port) {
            $this->port = (int)$port;
        }
    }
    
    /**
     * Parse Railway DATABASE_URL (PostgreSQL format)
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
        $this->host     = $parsed['host'] ?? 'localhost';
        $this->port     = isset($parsed['port']) ? (int)$parsed['port'] : 5432;
        $this->database = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'vtmoption';
        $this->charset  = 'utf8';
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
            // Build DSN
            if ($this->type === 'pgsql') {
                $port = $this->port ?? 5432;
                $dsn = "pgsql:host={$this->host};port={$port};dbname={$this->database}";
            } else {
                $port = $this->port ?? 3306;
                $dsn = "mysql:host={$this->host};port={$port};dbname={$this->database};charset={$this->charset}";
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
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function close(): void
    {
        $this->connection = null;
    }
    
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
            return (int)$this->getConnection()->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Insert failed: " . $e->getMessage());
        }
    }
    
    public function update(string $table, array $data, array $where, array $whereParams = []): int
    {
        $fields = array_keys($data);
        $setClause = implode(', ', array_map(fn($field) => "$field = :$field", $fields));
        
        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :where_$field", array_keys($where)));
        
        $sql = "UPDATE $table SET $setClause WHERE $whereClause";
        
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
    
    public function findById(string $table, int $id): ?array
    {
        return $this->queryOne("SELECT * FROM $table WHERE id = :id LIMIT 1", ['id' => $id]);
    }
    
    public function find(string $table, array $conditions = [], array $options = []): array
    {
        $sql = "SELECT * FROM $table";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($conditions)));
            $sql .= " WHERE $whereClause";
            $params = $conditions;
        }
        
        if (isset($options['orderBy'])) {
            $sql .= " ORDER BY " . $options['orderBy'];
        }
        
        if (isset($options['limit'])) {
            $sql .= " LIMIT " . (int)$options['limit'];
            
            if (isset($options['offset'])) {
                $sql .= " OFFSET " . (int)$options['offset'];
            }
        }
        
        return $this->query($sql, $params);
    }
    
    public function count(string $table, array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM $table";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($conditions)));
            $sql .= " WHERE $whereClause";
            $params = $conditions;
        }
        
        return (int)$this->queryValue($sql, $params);
    }
    
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }
    
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }
    
    public function lastInsertId(): int
    {
        return (int)$this->getConnection()->lastInsertId();
    }
    
    private function __clone() {}
    
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
