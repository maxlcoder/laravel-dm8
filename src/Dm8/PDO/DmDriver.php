<?php

namespace LaravelDm8\Dm8\PDO;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Driver\PDOConnection;

/**
 * DM8 PDO Driver.
 *
 * This driver provides PDO-based connectivity to DM8 database.
 * DM8 is compatible with Oracle syntax but uses its own PDO driver.
 *
 * @author DM8 Support Team
 */
class DmDriver extends AbstractOracleDriver
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'dm';
    }

    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        try {
            $dsn = $this->constructPdoDsn($params);

            return new PDOConnection(
                $dsn,
                $username,
                $password,
                $driverOptions
            );
        } catch (\PDOException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * Constructs the DM8 PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    private function constructPdoDsn(array $params)
    {
        // DM8 uses 'dm' as PDO driver prefix instead of 'oci'
        $dsn = 'dm:dbname=' . $this->getEasyConnectString($params);

        if (isset($params['charset'])) {
            $dsn .= ';charset=' . $params['charset'];
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new Dm8Platform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new Dm8SchemaManager($conn, $this->getDatabasePlatform());
    }
}
