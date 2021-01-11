# (Laravel) Twitch PubSub

[![Latest Version on Packagist](https://img.shields.io/packagist/v/danilopolani/twitch-pub-sub.svg?style=flat-square)](https://packagist.org/packages/danilopolani/twitch-pub-sub)
[![Build Status](https://travis-ci.com/danilopolani/twitch-pub-sub.svg)](https://travis-ci.com/danilopolani/twitch-pub-sub)
[![Total Downloads](https://img.shields.io/packagist/dt/danilopolani/twitch-pub-sub.svg?style=flat-square)](https://packagist.org/packages/danilopolani/twitch-pub-sub)

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
<details open="open" style="margin-top:20px;margin-bottom:40px">
  <summary><h2 style="display: inline-block">Table of Contents</h2></summary>
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
        <li>
          <a href="#topics-events">Topics & Events</a>
          <a href="#topics-events">Callbacks</a>
        </li>
      </ul>
    </li>
    <li><a href="#changelog">Changelog</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#testing">Testing</a></li>
    <li><a href="#security">Security</a></li>
    <li><a href="#credits">Credits</a></li>
    <li><a href="#license">License</a></li>
  </ol>
</details>

<!-- GETTING STARTED -->
## Getting Started

Rrequirements and installation of Twitch PubSub.

### Prerequisites

This package requires the PHP `ext-pcntl` to be installed and active.

### Installation

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

Now you can run your command.

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

|               Topic and Example                 | Event                                             |
|:-----------------------------------------------:|---------------------------------------------------|
| `channel-bits-events-v1.<channel_id>`           | `\Danilopolani\TwitchPubSub\BitsDonated`          |
| `channel-bits-events-v2.<channel_id>`           | `\Danilopolani\TwitchPubSub\BitsDonated`          |
| `channel-bits-badge-unlocks.<channel_id>`       | `\Danilopolani\TwitchPubSub\BitsBadgeUnlocked`    |
| `channel-points-channel-v1.<channel_id>`        | `\Danilopolani\TwitchPubSub\RewardRedeemed`       |
| `channel-subscribe-events-v1.<channel_id>`      | `\Danilopolani\TwitchPubSub\SubscriptionReceived` |
| `chat_moderator_actions.<user_id>.<channel_id>` | `\Danilopolani\TwitchPubSub\ModeratorActionSent`  |
| `whispers.<user_id>`                            | `\Danilopolani\TwitchPubSub\WhisperReceived`      |


### Callbacks

The package provides several callbacks fired when something occurs. These callbacks **must be put before** the `::run()` method to let them work correctly.

> Note: when the connection is closed the package will take care of it, restoring and reconnecting it (if possible).  

```php
// A message (anything, PING/PONG too for example) is received
TwitchPubSub::onMessage(function (array $payload) {
    dump('received message:', $payload);
});

// A generic error occurs
TwitchPubSub::onError(function (Throwable $e) {
    dump('generic error:', $e->getMessage());
});

// The connection has been closed
// This could triggered from a SIGINT or SIGTERM too (stopping the script, restarting the worker etc.)
TwitchPubSub::onClose(function (Throwable $e) {
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
