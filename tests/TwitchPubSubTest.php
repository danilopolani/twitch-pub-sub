<?php

namespace Danilopolani\TwitchPubSub\Tests;

use Danilopolani\TwitchPubSub\TwitchPubSub;
use Orchestra\Testbench\TestCase;
use Danilopolani\TwitchPubSub\TwitchPubSubServiceProvider;

class TwitchPubSubTest extends TestCase
{
    /** {@inheritDoc} */
    protected function getPackageProviders($app)
    {
        return [TwitchPubSubServiceProvider::class];
    }

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

    /**
     * Call a reserved method.
     *
     * @throws \Exception
     *
     * @param  mixed $object
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    private function callMethod($object, string $method , array $parameters = [])
    {
        try {
            $className = get_class($object);
            $reflection = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
           throw new \Exception($e->getMessage());
        }

        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
