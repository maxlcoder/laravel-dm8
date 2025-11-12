<?php

namespace LaravelDm8\Dm8\PDO;

use Doctrine\DBAL\Schema\OracleSchemaManager;
/**
 * Dm8SchemaManager.
 *
 * The Dm8SchemaManager class provides the schema management
 * operations for DM8 database.
 *
 * @author DM8 Support Team
 */
class Dm8SchemaManager extends OracleSchemaManager
{
    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return $this->_platform;
    }

    /**
     * {@inheritdoc}
     */
    public function listTableNames()
    {
        $sql = $this->_platform->getListTablesSQL();

        $tables = $this->_conn->fetchAll($sql);
        $tableNames = $this->_getPortableTablesList($tables);
        return $tableNames;
    }

    /**
     * {@inheritdoc}
     */
    public function _getPortableTablesList($tables) {
        return array_column($tables, 'table_name');
    }
}
