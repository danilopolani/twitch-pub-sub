<?php

namespace Danilopolani\TwitchPubSub\Facades;

use Danilopolani\TwitchPubSub\TwitchPubSub as TwitchPubSubBase;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void run(string|array $twitchAuthToken, array $topics = [])
 * @method static void onMessage(callable $fn)
 * @method static void onError(callable $fn)
 * @method static void onDispatchError(callable $fn)
 * @method static void onClose(callable $fn)
 *
 * @see \Danilopolani\TwitchPubSub\TwitchPubSub
 */
class TwitchPubSub extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return TwitchPubSubBase::class;
    }
}
