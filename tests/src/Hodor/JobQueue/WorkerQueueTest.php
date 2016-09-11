<?php

namespace Hodor\JobQueue;

use Exception;
use Hodor\Database\Adapter\Testing\Database;
use Hodor\Database\Adapter\Testing\Dequeuer;
use Hodor\Database\Exception\BufferedJobNotFoundException;
use Hodor\MessageQueue\Adapter\ConsumerInterface;
use Hodor\MessageQueue\Adapter\ProducerInterface;
use Hodor\MessageQueue\Adapter\Testing\Consumer;
use Hodor\MessageQueue\Adapter\Testing\MessageBank;
use Hodor\MessageQueue\Adapter\Testing\Producer;
use Hodor\MessageQueue\IncomingMessage;
use Hodor\MessageQueue\Queue;
use PHPUnit_Framework_TestCase;

/**
 * @coversDefaultClass Hodor\JobQueue\WorkerQueue
 */
class WorkerQueueTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var MessageBank
     */
    private $message_bank;

    /**
     * @var ConsumerInterface
     */
    private $consumer;

    /**
     * @var ProducerInterface
     */
    private $producer;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var WorkerQueue
     */
    private $worker_queue;

    /**
     * @var Database
     */
    private $database;

    public function setUp()
    {
        parent::setUp();

        $this->message_bank = new MessageBank();
        $this->consumer = new Consumer($this->message_bank);
        $this->producer = new Producer($this->message_bank);
        $this->queue = new Queue($this->consumer, $this->producer);
        $this->database = new Database();
        $this->worker_queue = new WorkerQueue(
            $this->queue,
            new Dequeuer($this->database)
        );
    }

    /**
     * @covers ::__construct
     * @covers ::push
     * @covers ::<private>
     */
    public function testJobCanBeQueued()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'   => "some-job-{$uniqid}",
            'params' => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);
        $this->consumer->consumeMessage(function (IncomingMessage $message) use ($expected_job) {
            $received_job = $message->getContent();
            $this->assertEquals($expected_job, [
                'name'   => $received_job['name'],
                'params' => $received_job['params'],
                'meta'   => $received_job['meta'],
            ]);
        });
    }

    /**
     * @covers ::__construct
     * @covers ::runNext
     * @covers ::<private>
     */
    public function testJobNameAndParamsArePassedToJobRunner()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'    => "some-job-{$uniqid}",
            'params'  => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->database->insert('queued_jobs', $expected_job['meta']['buffered_job_id'], []);
        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);

        $this->worker_queue->runNext(function ($name, array $params) use ($expected_job) {
            $this->assertSame(
                [
                    'name'   => $expected_job['name'],
                    'params' => $expected_job['params'],
                ],
                [
                    'name'   => $name,
                    'params' => $params,
                ]
            );
        });
    }

    /**
     * @covers ::__construct
     * @covers ::runNext
     * @covers ::<private>
     */
    public function testDatabaseRecordForJobMarkedAsSuccessfulIsMovedToSuccessfulJobs()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'    => "some-job-{$uniqid}",
            'params'  => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->database->insert(
            'queued_jobs',
            $expected_job['meta']['buffered_job_id'],
            [
                'job_name'   => $expected_job['name'],
                'job_params' => json_encode($expected_job['params']),
            ]
        );

        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);
        $this->worker_queue->runNext(function () {});

        $job = current($this->database->getAll('successful_jobs'));
        $this->assertEquals(
            [
                'job_name' => $expected_job['name'],
                'param'    => $expected_job['params']['value'],
                'meta'     => $expected_job['meta']['buffered_job_id'],
            ],
            [
                'job_name' => $job['job_name'],
                'param'    => json_decode($job['job_params'], true)['value'],
                'meta'     => $expected_job['meta']['buffered_job_id'],
            ]
        );
    }

    /**
     * @covers ::__construct
     * @covers ::runNext
     * @covers ::<private>
     * @expectedException Exception
     */
    public function testMessageForJobMarkedAsSuccessfulIsAcknowledged()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'    => "some-job-{$uniqid}",
            'params'  => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->database->insert('queued_jobs', $expected_job['meta']['buffered_job_id'], []);
        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);

        $this->worker_queue->runNext(function () {});
        $this->message_bank->emulateReconnect();
        $this->worker_queue->runNext(function () {});
    }

    /**
     * @covers ::__construct
     * @covers ::runNext
     * @covers ::<private>
     * @expectedException \Hodor\MessageQueue\Adapter\Testing\Exception\EmptyQueueException
     */
    public function testJobMarkedAsSuccessfulButNotAcknowledgedCanBeAcknowledgedSecondTime()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'    => "some-job-{$uniqid}",
            'params'  => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);

        try {
            $this->worker_queue->runNext(function () {});
        } catch (BufferedJobNotFoundException $exception) {
            $this->worker_queue->runNext(function () {});
        }
    }






    /**
     * @covers ::__construct
     * @covers ::runNext
     * @covers ::<private>
     */
    public function testDatabaseRecordForJobMarkedAsFailedIsMovedToFailedJobs()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'    => "some-job-{$uniqid}",
            'params'  => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->database->insert(
            'queued_jobs',
            $expected_job['meta']['buffered_job_id'],
            [
                'job_name'   => $expected_job['name'],
                'job_params' => json_encode($expected_job['params']),
            ]
        );

        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);
        try {
            $this->worker_queue->runNext(
                function () {
                    throw new Exception("Failed job");
                }
            );
        } catch (Exception $ex) {
            $job = current($this->database->getAll('failed_jobs'));
            $this->assertEquals(
                [
                    'job_name' => $expected_job['name'],
                    'param'    => $expected_job['params']['value'],
                    'meta'     => $expected_job['meta']['buffered_job_id'],
                ],
                [
                    'job_name' => $job['job_name'],
                    'param'    => json_decode($job['job_params'], true)['value'],
                    'meta'     => $expected_job['meta']['buffered_job_id'],
                ]
            );
        }
    }

    /**
     * @covers ::__construct
     * @covers ::runNext
     * @covers ::<private>
     * @expectedException Exception
     */
    public function testMessageForJobMarkedAsFailedIsAcknowledged()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'    => "some-job-{$uniqid}",
            'params'  => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->database->insert('queued_jobs', $expected_job['meta']['buffered_job_id'], []);
        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);

        $this->worker_queue->runNext(function () {
            throw new Exception("Failed job");
        });
        $this->message_bank->emulateReconnect();
        $this->worker_queue->runNext(function () {});
    }

    /**
     * @covers ::__construct
     * @covers ::runNext
     * @covers ::<private>
     * @expectedException \Hodor\MessageQueue\Adapter\Testing\Exception\EmptyQueueException
     */
    public function testJobMarkedAsFailedButNotAcknowledgedCanBeAcknowledgedSecondTime()
    {
        $uniqid = uniqid();
        $expected_job = [
            'name'    => "some-job-{$uniqid}",
            'params'  => ['value' => $uniqid],
            'meta'   => ['buffered_job_id' => rand(1, 10)],
        ];

        $this->worker_queue->push($expected_job['name'], $expected_job['params'], $expected_job['meta']);

        try {
            $this->worker_queue->runNext(function () {
                throw new Exception("Failed job");
            });
        } catch (BufferedJobNotFoundException $exception) {
            $this->worker_queue->runNext(function () {
                throw new Exception("Failed job");
            });
        }
    }
}
