<?php

namespace Hodor\MessageQueue;

use PhpAmqpLib\Channel\AMQPChannel;

class QueueFactory
{
    /**
     * @var array
     */
    private $connections = [];

    /**
     * @var array
     */
    private $channels = [];

    /**
     * @var Queue[]
     */
    private $queues = [];

    /**
     * @var bool
     */
    private $is_in_transaction = false;

    /**
     * @param array $queue_config
     * @return Queue
     */
    public function getQueue(array $queue_config)
    {
        $queue_name = $queue_config['queue_name'];

        if (isset($this->queues[$queue_name])) {
            return $this->queues[$queue_name];
        }

        $this->queues[$queue_name] = new Queue(
            $queue_config,
            $this->getAmqpChannel($queue_config)
        );
        if ($this->is_in_transaction) {
            $this->queues[$queue_name]->beginTransaction();
        }

        return $this->queues[$queue_name];
    }

    public function beginTransaction()
    {
        array_walk($this->queues, function (Queue $queue) {
            $queue->beginTransaction();
        });
        $this->is_in_transaction = true;
    }

    public function commitTransaction()
    {
        array_walk($this->queues, function (Queue $queue) {
            $queue->commitTransaction();
        });
        $this->is_in_transaction = false;
    }

    public function rollbackTransaction()
    {
        array_walk($this->queues, function (Queue $queue) {
            $queue->rollbackTransaction();
        });
        $this->is_in_transaction = false;
    }

    /**
     * @param  array  $queue_config
     * @return AMQPChannel
     */
    private function getAmqpChannel(array $queue_config)
    {
        $channel_key = $queue_config['queue_name'];

        if (isset($this->channels[$channel_key])) {
            return $this->channels[$channel_key];
        }

        $connection = $this->getAmqpConnection($queue_config);

        $channel = $connection->channel();

        $channel->queue_declare(
            $queue_config['queue_name'],
            false,
            ($is_durable = true),
            false,
            false
        );
        $channel->basic_qos(
            null,
            $queue_config['fetch_count'],
            null
        );

        $this->channels[$channel_key] = $channel;

        return $this->channels[$channel_key];
    }

    /**
     * @param  array  $queue_config
     * @return AMQPConnection
     */
    private function getAmqpConnection(array $queue_config)
    {
        $connection_key = $this->getConnectionKey($queue_config);

        if (isset($this->connections[$connection_key])) {
            return $this->connections[$connection_key];
        }

        $connection_class = '\PhpAmqpLib\Connection\AMQPConnection';
        if (isset($queue_config['connection_type'])
            && 'socket' === $queue_config['connection_type']
        ) {
            $connection_class = '\PhpAmqpLib\Connection\AMQPSocketConnection';
        }

        $this->connections[$connection_key] = new $connection_class(
            $queue_config['host'],
            $queue_config['port'],
            $queue_config['username'],
            $queue_config['password']
        );

        return $this->connections[$connection_key];
    }

    /**
     * @param  array  $queue_config
     * @return string
     */
    private function getConnectionKey(array $queue_config)
    {
        return implode(
            '::',
            [
                $queue_config['host'],
                $queue_config['port'],
                $queue_config['username'],
                $queue_config['queue_name'],
            ]
        );
    }
}
