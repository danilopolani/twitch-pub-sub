<?php

namespace Danilopolani\TwitchPubSub\Tests;

use Danilopolani\TwitchPubSub\TwitchPubSub;

class GetSubscriptionDataTest extends TestCase
{
    /** @test */
    public function getSubscriptionsData_null_if_topics_not_all_array()
    {
        /** @var TwitchPubSub $instance */
        $instance = $this->app->make(TwitchPubSub::class);

        $this->assertNull($this->callMethod($instance, 'getSubscriptionsData', [
            ['foo'],
        ]));
        $this->assertNull($this->callMethod($instance, 'getSubscriptionsData', [
            ['foo' => 'bar'],
        ]));
    }

    /** @test */
    public function getSubscriptionsData_returns_array_if_token_string()
    {
        /** @var TwitchPubSub $instance */
        $instance = $this->app->make(TwitchPubSub::class);

        $actual = $this->callMethod($instance, 'getSubscriptionsData', [
            'foo',
            ['bar', 'baz'],
        ]);

        $expected = [
            'foo' => ['bar', 'baz'],
        ];

        $this->assertSame($expected, $actual);
    }

    /** @test */
    public function getSubscriptionsData_returns_array_if_token_associative_array()
    {
        /** @var TwitchPubSub $instance */
        $instance = $this->app->make(TwitchPubSub::class);

        $actual = $this->callMethod($instance, 'getSubscriptionsData', [
            [
                'foo' => ['bar', 'baz'],
                'oof' => ['rab', 'zab'],
            ],
        ]);

        $expected = [
            'foo' => ['bar', 'baz'],
            'oof' => ['rab', 'zab'],
        ];

        $this->assertSame($expected, $actual);
    }
}
