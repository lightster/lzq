<?php

namespace Hodor\Database\Adapter\Postgres;

use Exception;
use PHPUnit_Framework_TestCase;

/**
 * @coversDefaultClass \Hodor\Database\Adapter\Postgres\Connection
 */
class ConnectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getYoPdoDriver
     * @covers ::getYoPdo
     */
    public function testYoPdoObjectIsUseable()
    {
        $connection = new Connection($this->getDbConfig());
        $yo_pdo = $connection->getYoPdo();

        $yo_pdo->queryMultiple('BEGIN');

        $this->assertSame(
            $yo_pdo->query('SELECT txid_current()')->fetch(),
            $yo_pdo->query('SELECT txid_current()')->fetch()
        );

        $yo_pdo->queryMultiple('ROLLBACK');
    }

    /**
     * @covers ::__construct
     * @covers ::getYoPdoDriver
     */
    public function testYoPdoDriverIsReused()
    {
        $connection = new Connection($this->getDbConfig());

        $this->assertSame(
            $connection->getYoPdoDriver(),
            $connection->getYoPdoDriver()
        );
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getDbConfig()
    {
        $config_path = __DIR__ . '/../../../../../../config/config.test.php';
        if (!file_exists($config_path)) {
            throw new Exception("'{$config_path}' not found");
        }

        $config = require $config_path;

        return $config['test']['db']['yo-pdo-pgsql'];
    }
}
