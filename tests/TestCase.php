<?php

namespace Danilopolani\TwitchPubSub\Tests;

use Danilopolani\TwitchPubSub\TwitchPubSubServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** {@inheritDoc} */
    protected function getPackageProviders($app)
    {
        return [TwitchPubSubServiceProvider::class];
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
    protected function callMethod($object, string $method, array $parameters = [])
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
