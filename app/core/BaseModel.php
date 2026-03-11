<?php
/**
 * BaseModel Class - Abstract base class for all models
 * 
 * Provides common database operations (CRUD) for all models
 */

namespace App\Core;

abstract class BaseModel
{
    protected $db;
    protected $table;
    protected $fillable = [];
    protected $primaryKey = 'id';
    protected $timestamps = true;
    protected $errors = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all records
     *
     * @param array $columns Columns to select
     * @param array $where Where conditions
     * @param string $orderBy Order by clause
     * @param int $limit Limit results
     * @return array
     */
    public function getAll($columns = ['*'], $where = [], $orderBy = null, $limit = null)
    {
        $query = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $this->table;

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', array_map(fn($key) => "$key = :$key", array_keys($where)));
        }

        if ($orderBy) {
            $query .= ' ORDER BY ' . $orderBy;
        }

        if ($limit) {
            $query .= ' LIMIT ' . intval($limit);
        }

        $this->db->prepare($query);
        
        if (!empty($where)) {
            foreach ($where as $key => $value) {
                $this->db->bind(':' . $key, $value);
            }
        }

        return $this->db->fetchAll();
    }

    /**
     * Find record by ID
     *
     * @param int $id Record ID
     * @return array|null
     */
    public function find($id)
    {
        return $this->findBy([$this->primaryKey => $id]);
    }

    /**
     * Find record where condition matches
     *
     * @param array $where Where conditions
     * @return array|null
     */
    public function findBy($where)
    {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE ';
        $query .= implode(' AND ', array_map(fn($key) => "$key = :$key", array_keys($where)));

        $this->db->prepare($query);
        foreach ($where as $key => $value) {
            $this->db->bind(':' . $key, $value);
        }

        return $this->db->fetch();
    }

    /**
     * Create new record
     *
     * @param array $data
     * @return mixed Insert ID or false
     */
    public function create($data)
    {
        // Filter fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));

        // Add timestamps
        if ($this->timestamps) {
            $filteredData['created_at'] = date(DATETIME_FORMAT);
            $filteredData['updated_at'] = date(DATETIME_FORMAT);
        }

        $columns = implode(', ', array_keys($filteredData));
        $placeholders = ':' . implode(', :', array_keys($filteredData));

        $query = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";

        $this->db->prepare($query);
        foreach ($filteredData as $key => $value) {
            $this->db->bind(':' . $key, $value);
        }

        if ($this->db->execute()) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update record
     *
     * @param int $id Record ID
     * @param array $data Data to update
     * @return bool
     */
    public function update($id, $data)
    {
        // Filter fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));

        // Add timestamp
        if ($this->timestamps) {
            $filteredData['updated_at'] = date(DATETIME_FORMAT);
        }

        $setClause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($filteredData)));
        $query = "UPDATE $this->table SET $setClause WHERE $this->primaryKey = :id";

        $this->db->prepare($query);
        foreach ($filteredData as $key => $value) {
            $this->db->bind(':' . $key, $value);
        }
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    /**
     * Delete record
     *
     * @param int $id Record ID
     * @return bool
     */
    public function delete($id)
    {
        $query = "DELETE FROM $this->table WHERE $this->primaryKey = :id";
        $this->db->prepare($query);
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Delete multiple records by condition
     *
     * @param array $where Where conditions
     * @return bool
     */
    public function deleteWhere($where)
    {
        $query = 'DELETE FROM ' . $this->table . ' WHERE ';
        $query .= implode(' AND ', array_map(fn($key) => "$key = :$key", array_keys($where)));

        $this->db->prepare($query);
        foreach ($where as $key => $value) {
            $this->db->bind(':' . $key, $value);
        }

        return $this->db->execute();
    }

    /**
     * Count records
     *
     * @param array $where Where conditions
     * @return int
     */
    public function count($where = [])
    {
        $query = 'SELECT COUNT(*) as count FROM ' . $this->table;

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', array_map(fn($key) => "$key = :$key", array_keys($where)));
        }

        $this->db->prepare($query);
        
        if (!empty($where)) {
            foreach ($where as $key => $value) {
                $this->db->bind(':' . $key, $value);
            }
        }

        $result = $this->db->fetch();
        return $result ? $result['count'] : 0;
    }

    /**
     * Get the table name for this model
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Paginate results
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $where Where conditions
     * @param string $orderBy Order by clause
     * @return array
     */
    public function paginate($page = 1, $perPage = ITEMS_PER_PAGE, $where = [], $orderBy = null)
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->count($where);
        $totalPages = ceil($total / $perPage);

        $items = $this->getAll(['*'], $where, $orderBy, "$offset, $perPage");

        return [
            'items' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
        ];
    }

    /**
     * Check if record exists
     *
     * @param array $where Where conditions
     * @return bool
     */
    public function exists($where)
    {
        return $this->count($where) > 0;
    }

    /**
     * Add validation error
     *
     * @param string $field Field name
     * @param string $message Error message
     */
    public function addError($field, $message)
    {
        $this->errors[$field] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if has errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Clear errors
     */
    public function clearErrors()
    {
        $this->errors = [];
    }

    /**
     * Execute raw query
     *
     * @param string $query SQL query
     * @param array $params Parameters
     * @return array|bool
     */
    public function rawQuery($query, $params = [])
    {
        $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }

        return $this->db->fetchAll();
    }
}
