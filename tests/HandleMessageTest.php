<?php

namespace Danilopolani\TwitchPubSub\Tests;

use Danilopolani\TwitchPubSub\TwitchPubSub;
use Mockery;
use Mockery\MockInterface;

class HandleMessageTest extends TestCase
{
    /** @test */
    public function handleMessage_without_type_returns()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldNotReceive('dispatchEvent');

        $payload = [];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** @test */
    public function handleMessage_with_type_not_message_returns()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldNotReceive('dispatchEvent');

        $payload = [
            'type' => 'wrong',
        ];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** @test */
    public function handleMessage_without_data_returns()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldNotReceive('dispatchEvent');

        $payload = [
            'type' => 'MESSAGE',
        ];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** @test */
    public function handleMessage_without_topic_returns()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldNotReceive('dispatchEvent');

        $payload = [
            'type' => 'MESSAGE',
            'data' => [],
        ];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** @test */
    public function handleMessage_without_message_returns()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldNotReceive('dispatchEvent');

        $payload = [
            'type' => 'MESSAGE',
            'data' => [
                'topic' => 'foo',
            ],
        ];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** @test */
    public function handleMessage_with_wrong_json_returns()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldNotReceive('dispatchEvent');

        $payload = [
            'type' => 'MESSAGE',
            'data' => [
                'topic' => 'foo',
                'message' => '{'
            ],
        ];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** @test */
    public function handleMessage_with_message_type_thread_returns()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldNotReceive('dispatchEvent');

        $payload = [
            'type' => 'MESSAGE',
            'data' => [
                'topic' => 'foo',
                'message' => json_encode([
                    'type' => 'thread',
                ])
            ],
        ];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** @test */
    public function handleMessage_dispatches_event()
    {
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldReceive('dispatchEvent')
             ->with('topic', ['foo' => 'bar']);

        $payload = [
            'type' => 'MESSAGE',
            'data' => [
                'topic' => 'topic',
                'message' => json_encode([
                    'foo' => 'bar',
                ])
            ],
        ];

        $this->callMethod($mock, 'handleMessage', [$payload]);
    }

    /** {@inheritDoc} */
    public function tearDown(): void
    {
        Mockery::close();
    }
}
