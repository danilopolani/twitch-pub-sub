# (Laravel) Twitch PubSub

[![Latest Version on Packagist](https://img.shields.io/packagist/v/danilopolani/twitch-pub-sub.svg?style=flat-square)](https://packagist.org/packages/danilopolani/twitch-pub-sub)
[![Build Status](https://travis-ci.com/danilopolani/twitch-pub-sub.svg)](https://travis-ci.com/danilopolani/twitch-pub-sub)

<!-- PROJECT LOGO -->
<br />
<p align="center">
  <a href="https://github.com/danilopolani/twitch-pub-sub">
    <img src="https://banners.beyondco.de/Twitch%20PubSub.png?theme=light&packageManager=composer+require&packageName=danilopolani%2Ftwitch-pub-sub&pattern=floatingCogs&style=style_1&description=Twitch+PubSub+Web+Socket+implementation+for+Laravel&md=1&showWatermark=1&fontSize=100px&images=switch-vertical">
  </a>
</p>

# Laravel Twitch PubSub

Connect to Twitch PubSub (Web Sockets) in a Laravel application, dispatching **Events** whenever a message for a topic is received.  

Built with [Amphp with Web Socket](https://amphp.org/websocket-client/).

<!-- TABLE OF CONTENTS -->
## Table of Contents
<ol>
  <li>
    <a href="#getting-started">Getting Started</a>
    <ul>
      <li><a href="#prerequisites">Prerequisites</a></li>
      <li><a href="#installation">Installation</a></li>
    </ul>
  </li>
  <li>
    <a href="#usage">Usage</a>
    <ul>
      <li><a href="#topics--events">Topics & Events</a></li>
      <li><a href="#reconnection">Reconnecting</a></li>
      <li><a href="#callbacks">Callbacks</a></li>
    </ul>
  </li>
  <li><a href="#changelog">Changelog</a></li>
  <li><a href="#contributing">Contributing</a></li>
  <li><a href="#testing">Testing</a></li>
  <li><a href="#security">Security</a></li>
  <li><a href="#credits">Credits</a></li>
  <li><a href="#license">License</a></li>
</ol>

<!-- GETTING STARTED -->
## Getting Started

The package supports `Laravel 8.x` and `PHP >= 7.4`.

### Prerequisites

The PHP extension `ext-pcntl` is required.

### Installation

You can install the package via composer:

```bash
composer require danilopolani/twitch-pub-sub
```

## Usage

The package relies on one main function:

```php
TwitchPubSub::run(string|array $twitchAuthToken, array $topics = [])
```


| Argument | Description |
| -------- | ----------- |
| `array\|string $twitchAuthToken` | if string, it must be a valid Auth Token, otherwise it can be an associative array of authToken => [topics](https://dev.twitch.tv/docs/pubsub#topics)[] |
| `array $topics` | an array of valid [topics](https://dev.twitch.tv/docs/pubsub#topics), needed only if `$twitchAuthToken` is a string |

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

Now you can run your command from your terminal or a worker.

> You should **definitely** [setup Supervisor](https://laravel.com/docs/8.x/queues#supervisor-configuration) or a similar tool to keep your command alive and restart it if something goes wrong.

Finally, create a [Listener](https://laravel.com/docs/8.x/events#defining-listeners) to handle the incoming events.

```php
// App\Providers\EventServiceProvider.php

/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    \Danilopolani\TwitchPubSub\Events\WhisperReceived::class => [
        TrackMessages::class,
    ],
];

// Or with a closure
Event::listen(function (\Danilopolani\TwitchPubSub\Events\WhisperReceived $event) {
    dd($event->data);
});

```

### Topics & Events

|               Topic                 | Event                                             |
|-----------------------------------------------|---------------------------------------------------|
| `channel-bits-events-v1.<channel_id>`           | `\Danilopolani\TwitchPubSub\Events\BitsDonated`          |
| `channel-bits-events-v2.<channel_id>`           | `\Danilopolani\TwitchPubSub\Events\BitsDonated`          |
| `channel-bits-badge-unlocks.<channel_id>`       | `\Danilopolani\TwitchPubSub\Events\BitsBadgeUnlocked`    |
| `channel-points-channel-v1.<channel_id>`        | `\Danilopolani\TwitchPubSub\Events\RewardRedeemed`       |
| `channel-subscribe-events-v1.<channel_id>`      | `\Danilopolani\TwitchPubSub\Events\SubscriptionReceived` |
| `chat_moderator_actions.<user_id>.<channel_id>` | `\Danilopolani\TwitchPubSub\Events\ModeratorActionSent`  |
| `whispers.<user_id>`                            | `\Danilopolani\TwitchPubSub\Events\WhisperReceived`      |

### Reconnection

When the connection is closed, the package itself will try to attempt a reconnection, but this would need a **fresh access token**, furthermore we **strongly suggest you** to handle the `onClose` callback and exit your script. This, with a correct configuration of [**Supervisor**](https://laravel.com/docs/8.x/queues#supervisor-configuration), will restart the worker automatically reconnecting with a fresh token, if your code is written in that way. Below a simple example with a correct flow to demonstrate how it should work:

```php
// App/Console/Commands/PubSub.php

public function handle()
{
    // A fresh Twitch Access Token
    $token = $user->getFreshAccessToken();
    
    TwitchPubSub::onClose(function (\Amp\Websocket\ClosedException $e) {
        exit(0);
    });
    
    TwitchPubSub::run($token, ['my-topic']);
}
```

When `exit(0)` will be executed, the script will stop, Supervisor will restart it - invoking `handle` again - and refreshing the token reconnecting correctly.  
Please see below for more information about callbacks.

### Callbacks

The package provides several callbacks fired when something occurs. These callbacks **must be put before** the `::run()` method to let them work correctly.

```php
// A message (anything, PING/PONG too for example) is received
TwitchPubSub::onMessage(function (array $payload) {
    dump('received message:', $payload);
});

// A generic error occurs
TwitchPubSub::onError(function (\Exception $e) {
    dump('generic error:', $e->getMessage());
});

// The connection has been closed
// This could triggered from a SIGINT or SIGTERM too (stopping the script, restarting the worker etc.)
TwitchPubSub::onClose(function (\Amp\Websocket\ClosedException $e) {
    dump('connection closed, reason:', $e->getMessage());
});

// An error occurred in a Listener after the event has been dispatched
TwitchPubSub::onDispatchError(function (string $event, array $payload, Throwable $e) {
    dump('error for event', $event, $payload, $e->getMessage());
});

// Runner
TwitchPubSub::run('a1b2c3d4e5', ['whispers.44322889']);

```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Testing

Clone the repository and just run

``` bash
composer test
```

With Docker (Windows):

```bash
docker run --rm -v %cd%:/app composer:2 bash -c "cd /app && composer install --ignore-platform-reqs && ./vendor/bin/phpunit"
```

With Docker (Linux/OSX):

```bash
docker run --rm -v $(pwd):/app composer:2 bash -c "cd /app && composer install --ignore-platform-reqs && ./vendor/bin/phpunit"
```

## Security

If you discover any security related issues, please email danilo.polani@gmail.com instead of using the issue tracker.

## Credits

- [Danilo Polani](https://github.com/danilopolani)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
