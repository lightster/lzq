<?php

namespace Hodor\MessageQueue;

use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit_Framework_TestCase;

class MessageTest extends PHPUnit_Framework_TestCase
{
    public function testMessageCanBeInstantiated()
    {
        $this->assertInstanceOf(
            'Hodor\MessageQueue\Message',
            new Message(new AMQPMessage())
        );
    }

    public function testMessageContentCanBeRetrieved()
    {
        $expected_value = 'some_string';
        $message = $this->getBasicMessage($expected_value);

        $this->assertEquals($expected_value, $message->getContent());
    }

    public function testMessageContentCanBeRetrievedMultipleTimes()
    {
        $message = $this->getBasicMessage('some_other_string');

        $this->assertEquals($message->getContent(), $message->getContent());
    }

    public function testJsonMessageContentIsDecodedIfContentTypeIsAppropriatelySet()
    {
        $expected_value = ['a' => 1, 'b' => ['c' => 2]];

        $body = json_encode($expected_value);
        $properties = ['content_type' => 'application/json'];
        $amqp_message = new AMQPMessage($body, $properties);
        $message = new Message($amqp_message);

        $this->assertEquals($expected_value, $message->getContent());
    }

    public function testAmqpMessageIsAcknowledgedWhenMessageIsAcknowledged()
    {
        $message = $this->getAcknowledgeableMessage();

        $message->acknowledge();
    }

    public function testAmqpMessageIsOnlyAcknowledgedOnce()
    {
        $message = $this->getAcknowledgeableMessage();

        $message->acknowledge();
        $message->acknowledge();
    }

    /**
     * @param $body
     * @return Message
     */
    private function getBasicMessage($body)
    {
        return new Message(new AMQPMessage($body));
    }

    /**
     * @return Message
     */
    private function getAcknowledgeableMessage()
    {
        $delivery_tag = 'hey_there!';

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
            ->disableOriginalConstructor()
            ->setMethods(['basic_ack'])
            ->getMock();
        $channel->expects($this->once())
            ->method('basic_ack')
            ->with($delivery_tag);

        $amqp_message = new AMQPMessage();
        $amqp_message->delivery_info = [
            'channel'      => $channel,
            'delivery_tag' => $delivery_tag,
        ];
        return new Message($amqp_message);
    }
}