<?php

namespace Hodor\Database\Adapter\Postgres;

use Exception;
use Hodor\Database\Adapter\FactoryInterface;
use Hodor\Database\Adapter\FactoryTest as FactoryBaseTest;
use Hodor\Database\PgsqlAdapter;
use Hodor\Database\Phpmig\CommandWrapper;
use Hodor\Database\Phpmig\Container;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @coversDefaultClass \Hodor\Database\Adapter\Postgres\Factory
 */
class FactoryTest extends FactoryBaseTest
{
    /**
     * @var Factory
     */
    private $factory;

    public function setUp()
    {
        $phpmig_container = new Container();
        $phpmig_container->addDefaultServices('no-config-file');
        $phpmig_container['hodor.database'] = $this->getTestFactory()->getPgsqlAdapter();

        $command_wrapper = new CommandWrapper($phpmig_container, new NullOutput());
        $command_wrapper->rollbackMigrations();
        $command_wrapper->runMigrations();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->factory = null;

        // without forcing garbage collection, the DB connections
        // are not guaranteed to be disconnected; force GC
        gc_collect_cycles();
    }

    /**
     * @return Factory
     * @throws Exception
     */
    protected function getTestFactory()
    {
        if ($this->factory) {
            return $this->factory;
        }

        $this->factory = $this->generateTestFactory();

        return $this->factory;
    }

    /**
     * @return Factory
     * @throws Exception
     */
    private function generateTestFactory()
    {
        $config_path = __DIR__ . '/../../../../../../config/config.test.php';
        if (!file_exists($config_path)) {
            throw new Exception("'{$config_path}' not found");
        }

        $config = require $config_path;

        return new Factory(
            new PgsqlAdapter($config['test']['db']['yo-pdo-pgsql']),
            $config['test']['db']['yo-pdo-pgsql']
        );
    }
}
