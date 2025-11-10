<?php

namespace LaravelDm8\Dm8\SchemaManager;

use Illuminate\Database\Connection;
use LaravelDm8\Dm8\Dm8Connection;
use LaravelDm8\Dm8\SchemaManager\DmColumn;

/**
 * DM8 Schema Manager
 * 
 * Provides schema introspection methods for DM8 database.
 */
class DmSchemaManager
{
    /**
     * The database connection instance.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * The schema prefix.
     *
     * @var string
     */
    protected $schema;

    /**
     * Create a new schema manager instance.
     *
     * @param  \Illuminate\Database\Connection  $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        if ($connection instanceof Dm8Connection) {
            $this->schema = $connection->getSchema();
        } else {
            $this->schema = $connection->getConfig('database');
        }
    }

    /**
     * List all table columns.
     *
     * @param  string  $table
     * @return array
     */
    public function listTableColumns($table)
    {
        $table = strtoupper($table);
        
        $sql = "SELECT 
                    COLUMN_NAME,
                    DATA_TYPE,
                    DATA_LENGTH,
                    DATA_PRECISION,
                    DATA_SCALE,
                    NULLABLE,
                    DATA_DEFAULT,
                    COLUMN_ID
                FROM ALL_TAB_COLUMNS
                WHERE OWNER = UPPER(?)
                    AND TABLE_NAME = UPPER(?)
                ORDER BY COLUMN_ID";

        $results = $this->connection->select($sql, [$this->schema, $table]);
        return $this->processColumnResults($results);
    }

    /**
     * Process column results into structured format.
     *
     * @param  array  $results
     * @return array
     */
    protected function processColumnResults($results)
    {
        $columns = [];

        foreach ($results as $row) {
            $columnName = strtolower($row->COLUMN_NAME ?? $row->column_name);
            $dataType = strtolower($row->DATA_TYPE ?? $row->data_type);
            
            $columns[$columnName] = [
                'name' => $columnName,
                'type' => $dataType,
                'length' => $row->DATA_LENGTH ?? $row->data_length,
                'precision' => $row->DATA_PRECISION ?? $row->data_precision,
                'scale' => $row->DATA_SCALE ?? $row->data_scale,
                'nullable' => ($row->NULLABLE ?? $row->nullable) === 'Y',
                'default' => $this->parseDefaultValue($row->DATA_DEFAULT ?? $row->data_default),
            ];
        }

        return $columns;
    }

    /**
     * Get a single column information.
     *
     * @param  string  $table
     * @param  string  $column
     * @return \LaravelDm8\Dm8\SchemaManager\DmColumn|null
     */
    public function getColumn($table, $column)
    {
        $columns = $this->listTableColumns($table);
        $columnLower = strtolower($column);
        
        if (!isset($columns[$columnLower])) {
            return null;
        }

        return new DmColumn($columns[$columnLower]);
    }

    /**
     * Parse default value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function parseDefaultValue($value)
    {
        if (is_null($value)) {
            return null;
        }

        $value = trim($value);
        
        // Remove quotes if present
        if (preg_match("/^'(.*)'$/", $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    /**
     * List all table indexes.
     *
     * @param  string  $table
     * @return array
     */
    public function listTableIndexes($table)
    {
        $sql = "SELECT 
                    INDEX_NAME,
                    UNIQUENESS,
                    INDEX_TYPE
                FROM ALL_INDEXES
                WHERE TABLE_OWNER = UPPER(?)
                    AND TABLE_NAME = UPPER(?)
                ORDER BY INDEX_NAME";

        $indexes = $this->connection->select($sql, [$this->schema, strtoupper($table)]);

        return array_map(function ($index) {
            $indexName = $index->INDEX_NAME ?? $index->index_name;
            $indexNameLower = strtolower($indexName);
            
            return [
                'name' => $indexNameLower,
                'unique' => strtoupper($index->UNIQUENESS ?? $index->uniqueness) === 'UNIQUE',
                'type' => strtolower($index->INDEX_TYPE ?? $index->index_type),
            ];
        }, $indexes);
    }
}
