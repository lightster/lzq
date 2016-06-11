<?php

namespace Hodor\MessageQueue\Adapter;

use Hodor\MessageQueue\IncomingMessage;

interface ConfigInterface
{
    /**
     * @return FactoryInterface
     */
    public function getAdapterFactory();

    /**
     * @param string $queue_name
     * @return array
     */
    public function getQueueConfig($queue_name);
}
