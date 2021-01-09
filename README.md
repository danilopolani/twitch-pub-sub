# (Laravel) Twitch PubSub

[![Latest Version on Packagist](https://img.shields.io/packagist/v/danilopolani/twitch-pub-sub.svg?style=flat-square)](https://packagist.org/packages/danilopolani/twitch-pub-sub)
[![Build Status](https://travis-ci.com/danilopolani/twitch-pub-sub.svg)](https://travis-ci.com/danilopolani/twitch-pub-sub)
[![Total Downloads](https://img.shields.io/packagist/dt/danilopolani/twitch-pub-sub.svg?style=flat-square)](https://packagist.org/packages/danilopolani/twitch-pub-sub)

A simple package to connect to Twitch PubSub (Web Sockets) in a Laravel application, dispatching **Events** whenever a message for a topic is received.  

It uses [Amphp with Web Socket](https://amphp.org/websocket-client/) support to handle the connection.

## Installation

You can install the package via composer:

```bash
composer require danilopolani/twitch-pub-sub
```

## Usage

There's only one needed function to let the package work: `::run(string|array $twitchAuthToken, array $topics = [])`.

- `array|string $twitchAuthToken`: if string, it must be a valid Auth Token, otherwise it can be an associative array of authToken => [topics](https://dev.twitch.tv/docs/pubsub#topics)[].
- `array $topics`: an array of valid [topics](https://dev.twitch.tv/docs/pubsub#topics) used when `$twitchAuthToken` is a string.

Usually you would put the main function of the package inside an [Artisan Command](https://laravel.com/docs/8.x/artisan#writing-commands).

``` php

use \Danilopolani\TwitchPubSub\Facades\TwitchPubSub;

/**
 * Execute the console command.
 *
 * @return mixed
 */
public function handle()
{
    TwitchPubSub::run('a1b2c3d4e5', ['whispers.44322889']);

    // Or the array syntax that support multiple users too
    TwitchPubSub::run([
        'a1b2c3d4e5' => ['whispers.44322889'],
        'f6g7h8j9k0' => ['channel-bits-events-v1.123456', 'channel-points-channel-v1.123456'],
    ]);
}
```

Now you can run your command, you should **definitely** [setup Supervisor](https://laravel.com/docs/8.x/queues#supervisor-configuration) or a similar tool to keep your command alive.

Finally, create a [Listener](https://laravel.com/docs/8.x/events#defining-listeners) to handle the incoming events.

```php
// App\Providers\EventServiceProvider.php

/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    \Danilopolani\TwitchPubSub\WhisperReceived::class => [
        TrackMessages::class,
    ],
];

// Or with a closure
Event::listen(function (\Danilopolani\TwitchPubSub\WhisperReceived $event) {
    dd($event->data);
});

```

### List of topics with related events

|               Topic and Example                 | Event                                             |
|:-----------------------------------------------:|---------------------------------------------------|
| `channel-bits-events-v1.<channel_id>`           | `\Danilopolani\TwitchPubSub\BitsDonated`          |
| `channel-bits-events-v2.<channel_id>`           | `\Danilopolani\TwitchPubSub\BitsDonated`          |
| `channel-bits-badge-unlocks.<channel_id>`       | `\Danilopolani\TwitchPubSub\BitsBadgeUnlocked`    |
| `channel-points-channel-v1.<channel_id>`        | `\Danilopolani\TwitchPubSub\RewardRedeemed`       |
| `channel-subscribe-events-v1.<channel_id>`      | `\Danilopolani\TwitchPubSub\SubscriptionReceived` |
| `chat_moderator_actions.<user_id>.<channel_id>` | `\Danilopolani\TwitchPubSub\ModeratorActionSent`  |
| `whispers.<user_id>`                            | `\Danilopolani\TwitchPubSub\WhisperReceived`      |

## Testing

``` bash
composer test
```

With Docker (Windows):

```bash
docker run --rm -v %cd%:/app composer:2 bash -c "cd /app && composer install && ./vendor/bin/phpunit"
```

With Docker (Linux/OSX):

```bash
docker run --rm -v $(pwd):/app composer:2 bash -c "cd /app && composer install && ./vendor/bin/phpunit"
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

# Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email danilo.polani@gmail.com instead of using the issue tracker.

## Credits

- [Danilo Polani](https://github.com/danilopolani)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
