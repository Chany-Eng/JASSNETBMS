<?php
/**
 * Database Class - PDO Wrapper for Database Operations
 * 
 * Provides a clean interface for database operations with prepared statements
 */

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance;
    private $connection;
    private $statement;
    private $queryCount = 0;
    private $lastError;

    /**
     * Get singleton instance of Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize database connection
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Connect to database using PDO
     */
    private function connect()
    {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            
            $this->connection = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => false,
                ]
            );

            if (APP_DEBUG) {
                error_log('Database connected successfully');
            }
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            if (APP_DEBUG) {
                error_log('Database Connection Error: ' . $e->getMessage());
            }

            throw new \RuntimeException(
                APP_DEBUG
                    ? 'Database connection failed: ' . $e->getMessage()
                    : 'Database connection failed. Please contact administrator.'
            );
        }
    }

    /**
     * Get PDO connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Prepare a statement
     *
     * @param string $query SQL query
     * @return self
     */
    public function prepare($query)
    {
        try {
            $this->statement = $this->connection->prepare($query);
            $this->queryCount++;
            return $this;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Bind parameters to prepared statement
     *
     * @param string $param Parameter name or position
     * @param mixed $value Parameter value
     * @param int $type PDO type constant
     * @return self
     */
    public function bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            $type = match(gettype($value)) {
                'boolean' => PDO::PARAM_BOOL,
                'integer' => PDO::PARAM_INT,
                'NULL' => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
        }

        $this->statement->bindValue($param, $value, $type);
        return $this;
    }

    /**
     * Bind multiple parameters at once
     *
     * @param array $params Array of parameters [param => value]
     * @return self
     */
    public function bindArray($params)
    {
        foreach ($params as $param => $value) {
            $this->bind($param, $value);
        }
        return $this;
    }

    /**
     * Execute the prepared statement
     *
     * @return bool
     */
    public function execute()
    {
        try {
            return $this->statement->execute();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Get single result
     *
     * @return array|null
     */
    public function fetch()
    {
        $this->execute();
        return $this->statement->fetch();
    }

    /**
     * Get all results
     *
     * @return array
     */
    public function fetchAll()
    {
        $this->execute();
        return $this->statement->fetchAll();
    }

    /**
     * Get row count from last query
     *
     * @return int
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }

    /**
     * Get last insert ID
     *
     * @return string
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Get last error message
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Get query count
     *
     * @return int
     */
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->connection->rollback();
    }

    /**
     * Sanitize input string
     *
     * @param string $data
     * @return string
     */
    public function sanitize($data)
    {
        return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup() {}
}
