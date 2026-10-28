<?php

namespace LaravelDm8\Dm8\Query\Processors;

use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;

class DmProcessor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string  $sequence
     * @return int
     */
     public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
     {
         if ($sequence === null || $sequence === '') {
             return parent::processInsertGetId($query, $sql, $values, null);
         }

         $connection = $query->getConnection();

         $connection->recordsHaveBeenModified();
         $start = microtime(true);

         $id = 0;
         $parameter = 1;
         $statement = $this->prepareStatement($query, $sql);
         $parameter = $this->bindValues($values, $statement, $parameter);
         $statement->bindParam($parameter, $id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, PHP_INT_SIZE);
         $statement->execute();

         $values[] = '?';
         $connection->logQuery($sql, $values, (microtime(true) - $start) * 1000);

         return (int) $id;
     }

    /**
     * Get prepared statement.
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @return \PDOStatement|\Yajra\Pdo\Oci8
     */
    private function prepareStatement(Builder $query, $sql)
    {
        /** @var \LaravelDm8\Dm8\Dm8Connection $connection */
        $connection = $query->getConnection();
        $pdo = $connection->getPdo();

        return $pdo->prepare($sql);
    }

    /**
     * Bind values to PDO statement.
     *
     * @param  array  $values
     * @param  \PDOStatement  $statement
     * @param  int  $parameter
     * @return int
     */
    private function bindValues(&$values, $statement, $parameter)
    {
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            if (is_object($values[$i])) {
                if ($values[$i] instanceof DateTime) {
                    $values[$i] = $values[$i]->format('Y-m-d H:i:s');
                } else {
                    $values[$i] = (string) $values[$i];
                }
            }
            $type = $this->getPdoType($values[$i]);
            $statement->bindParam($parameter, $values[$i], $type);
            $parameter++;
        }

        return $parameter;
    }

    /**
     * Get PDO Type depending on value.
     *
     * @param  mixed  $value
     * @return int
     */
    private function getPdoType($value)
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }

        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }

        if (is_null($value)) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Save Query with Blob returning primary key value.
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  array  $binaries
     * @return int
     */
    public function saveLob(Builder $query, $sql, array $values, array $binaries)
    {
        $connection = $query->getConnection();

        $connection->recordsHaveBeenModified();
        $start = microtime(true);

        $id = 0;
        $parameter = 1;
        $statement = $this->prepareStatement($query, $sql);

        $parameter = $this->bindValues($values, $statement, $parameter);

        $countBinary = count($binaries);
        for ($i = 0; $i < $countBinary; $i++) {
            $statement->bindParam($parameter, $binaries[$i], PDO::PARAM_LOB);
            $parameter++;
        }

        // bind output param for the returning clause.
        $statement->bindParam($parameter, $id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, PHP_INT_SIZE);

        if (! $statement->execute()) {
            return false;
        }

        $connection->logQuery($sql, $values, (microtime(true) - $start) * 1000);

        return (int) $id;
    }

    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        $mapping = function ($r) {
            $r = (object) $r;

            return strtolower($r->column_name);
        };

        return array_map($mapping, $results);
    }
}
