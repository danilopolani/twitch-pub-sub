<?php

namespace Danilopolani\TwitchPubSub\Tests;

use Danilopolani\TwitchPubSub\Events\WhisperReceived;
use Danilopolani\TwitchPubSub\TwitchPubSub;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class DispatchEventTest extends TestCase
{
    /** @test */
    public function dispatchEvent_with_wrong_topic_returns_false()
    {
        Log::shouldReceive('warning')->with('[TwitchPubSub] Event name not found for topic "foo"');

        /** @var TwitchPubSub $instance */
        $instance = $this->app->make(TwitchPubSub::class);
        $payload = ['foo' => 'bar'];

        $this->assertFalse($this->callMethod($instance, 'dispatchEvent', ['foo', $payload]));
    }

    /** @test */
    public function dispatchEvent_with_event_not_found_returns_false()
    {
        Log::shouldReceive('warning')->with('[TwitchPubSub] Event class not found for event "foo"');

        /** @var TwitchPubSub $instance */
        $mock = $this->partialMock(TwitchPubSub::class);
        $mock->shouldAllowMockingProtectedMethods()
             ->shouldReceive('getEventName')
             ->andReturn('foo');

        $payload = ['foo' => 'bar'];

        $this->assertFalse($this->callMethod($mock, 'dispatchEvent', ['foo', $payload]));
    }

    /** @test */
    public function dispatchEvent_with_listener_error_returns_false()
    {
        Log::shouldReceive('warning')->with('[TwitchPubSub] Listener for event "WhisperReceived" threw an error: foo');

        Event::listen(function (WhisperReceived $event) {
            throw new \Exception('foo');
        });

        /** @var TwitchPubSub $instance */
        $instance = resolve(TwitchPubSub::class);
        $payload = ['foo' => 'bar'];

        $this->assertFalse($this->callMethod($instance, 'dispatchEvent', ['whispers.44322889', $payload]));
    }

    /** @test */
    public function dispatchEvent_returns_true()
    {
        $payload = ['foo' => 'bar'];

        Event::listen(function (WhisperReceived $event) use ($payload) {
            $this->assertSame($payload, $event->data);
        });

        /** @var TwitchPubSub $instance */
        $instance = resolve(TwitchPubSub::class);

        $this->assertTrue($this->callMethod($instance, 'dispatchEvent', ['whispers.44322889', $payload]));
    }
}
