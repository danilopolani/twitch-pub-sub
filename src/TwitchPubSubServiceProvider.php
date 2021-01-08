<?php

namespace Danilopolani\TwitchPubSub;

use Illuminate\Support\ServiceProvider;

class TwitchPubSubServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        // Register the main class to use with the facade
        $this->app->bind(TwitchPubSub::class);
    }
}
