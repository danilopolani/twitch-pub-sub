<?php

namespace Danilopolani\TwitchPubSub;

use function Amp\call as ampCall;

use Amp\Emitter;
use Amp\Loop;
use Amp\Websocket\Client;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Client\Handshake;
use Amp\Websocket\ClosedException;
use Amp\Websocket\Options;
use Exception;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TwitchPubSub
{
    protected EventsDispatcher $events;
    protected array $subscriptions;
    private Connection $connection;
    private Emitter $messagesEmitter;
    private Emitter $errorsEmitter;
    private Emitter $dispatchErrorsEmitter;
    private Emitter $connectionClosedEmitter;

    /**
     * Class constructor.
     *
     * @var EventsDispatcher
     */
    public function __construct(EventsDispatcher $events)
    {
        $this->events = $events;
        $this->messagesEmitter = new Emitter();
        $this->errorsEmitter = new Emitter();
        $this->dispatchErrorsEmitter = new Emitter();
        $this->connectionClosedEmitter = new Emitter();
    }

    /**
     * Run the websocket listener.
     *
     * @throws Exception
     *
     * @param  string|array $twitchAuthToken - Token or array of tokens with their topics
     * @param  array $topics
     * @return void
     */
    public function run($twitchAuthToken, array $topics = []): void
    {
        if (! $this->subscriptions = $this->getSubscriptionsData($twitchAuthToken, $topics)) {
            throw new Exception('Subscriptions array is not valid. It must be an associative array with Auth Token (key) and topics (value).');
        }

        Loop::run(function () {
            yield $this->connect();

            // Ping every minute
            Loop::repeat(60 * 1000, fn () => $this->ping());

            // Handle SIGINT to unlisten
            Loop::onSignal(SIGINT, function () {
                yield $this->unlisten();
                yield Loop::stop();
            });

            // Handle SIGINT to unlisten
            Loop::onSignal(SIGTERM, function () {
                yield $this->unlisten();
                yield Loop::stop();
            });

            try {
                /** @var \Amp\Websocket\Message $message */
                while ($message = yield $this->connection->receive()) {
                    $payload = json_decode(yield $message->buffer(), true);

                    // Stop if cannot decode payload
                    if (is_null($payload)) {
                        continue;
                    }

                    $this->messagesEmitter->emit($payload);

                    // Handle RECONNECT
                    if ($payload['type'] === 'RECONNECT') {
                        yield $this->connect();

                        continue;
                    }

                    $this->handleMessage($payload);
                }
            } catch (ClosedException $e) {
                $this->connectionClosedEmitter->emit($e);
                yield $this->connect();
            } catch (Throwable $e) {
                $this->errorsEmitter->emit($e);
            }
        });
    }

    /**
     * Messages event handler.
     *
     * @param callable $fn
     * @return void
     */
    public function onMessage(callable $fn)
    {
        Loop::run(function () use ($fn) {
            $iterator = $this->messagesEmitter->iterate();

            while (yield $iterator->advance()) {
                $fn($iterator->getCurrent());
            }
        });
    }

    /**
     * Dispatch errors event handler.
     *
     * @param callable $fn
     * @return void
     */
    public function onDispatchError(callable $fn)
    {
        Loop::run(function () use ($fn) {
            $iterator = $this->dispatchErrorsEmitter->iterate();

            while (yield $iterator->advance()) {
                $fn(...$iterator->getCurrent());
            }
        });
    }

    /**
     * Connection closed event handler.
     *
     * @param callable $fn
     * @return void
     */
    public function onClose(callable $fn)
    {
        Loop::run(function () use ($fn) {
            $iterator = $this->connectionClosedEmitter->iterate();

            while (yield $iterator->advance()) {
                $fn($iterator->getCurrent());
            }
        });
    }

    /**
     * Errors event handler.
     *
     * @param callable $fn
     * @return void
     */
    public function onError(callable $fn)
    {
        Loop::run(function () use ($fn) {
            $iterator = $this->errorsEmitter->iterate();

            while (yield $iterator->advance()) {
                $fn($iterator->getCurrent());
            }
        });
    }

    /**
     * Connect to websocket.
     *
     * @return void
     */
    protected function connect()
    {
        // Connect with manual ping
        $options = Options::createClientDefault()->withoutHeartbeat();
        $handshake = new Handshake('wss://pubsub-edge.twitch.tv', $options);

        return ampCall(function () use ($handshake) {
            $this->connection = yield Client\connect($handshake);
            yield $this->listen($this->connection);
        });
    }

    /**
     * Ping web socket.
     *
     * @return mixed
     */
    protected function ping()
    {
        return yield $this->connection->send(json_encode([
            'type' => 'PING',
        ]));
    }

    /**
     * Listen for topics.
     *
     * @return \Amp\Promise[]
     */
    protected function listen(Connection $connection): array
    {
        $promises = [];

        foreach ($this->subscriptions as $token => $topics) {
            $promises[] = $connection->send(json_encode([
                'type' => 'LISTEN',
                'data' => [
                    'topics' => $topics,
                    'auth_token' => $token,
                ],
            ]));
        }

        return $promises;
    }

    /**
     * Unlisten topics.
     *
     * @return \Amp\Promise[]
     */
    protected function unlisten(): array
    {
        $promises = [];

        foreach ($this->subscriptions as $token => $topics) {
            $promises[] = $this->connection->send(json_encode([
                'type' => 'UNLISTEN',
                'data' => [
                    'topics' => $topics,
                    'auth_token' => $token,
                ],
            ]));
        }

        return $promises;
    }

    /**
     *  Handle a message.
     *
     * @param  array $payload
     * @return void
     */
    protected function handleMessage(array $payload): void
    {
        // Skip response, heartbeat etc. and if there's no topic key or message
        if (Arr::get($payload, 'type') !== 'MESSAGE' || ! Arr::has($payload, 'data.message') || ! $topic = Arr::get($payload, 'data.topic')) {
            return;
        }

        $message = json_decode($payload['data']['message'], true);

        if (! is_array($message)) {
            return;
        }

        if (isset($message['data'])) {
            $message['data'] = json_decode($message['data'], true);
        }

        // Skip threads for whispers
        if (Arr::get($message, 'type') === 'thread') {
            return;
        }

        // Dispatch event
        $this->dispatchEvent($topic, $message);
    }

    /**
     * Get an event name from the topic.
     *
     * @param  string $topic
     * @return string|null
     */
    protected function getEventName(string $topic): ?string
    {
        $events = [
            'channel-bits-events' => 'BitsDonated',
            'channel-bits-badge-unlocks' => 'BitsBadgeUnlocked',
            'channel-points-channel' => 'RewardRedeemed',
            'channel-subscribe-events' => 'SubscriptionReceived',
            'chat_moderator_actions' => 'ModeratorActionSent',
            'whispers' => 'WhisperReceived',
        ];

        $topicPurified = (string) Str::of($topic)->before('.')->replaceMatches('/\-v[\d+]/', '');

        return $events[$topicPurified] ?? null;
    }

    /**
     * Dispatch an event for a topic.
     *
     * @param  string $topic
     * @param  array $data
     * @return bool
     */
    protected function dispatchEvent(string $topic, array $data): bool
    {
        if (! $eventName = $this->getEventName($topic)) {
            Log::warning('[TwitchPubSub] Event name not found for topic "'.$topic.'"');

            return false;
        }

        $className = '\Danilopolani\TwitchPubSub\Events\\'.$eventName;
        if (! class_exists($className)) {
            Log::warning('[TwitchPubSub] Event class not found for event "'.$eventName.'"');

            return false;
        }

        // Wrap dispatcher inside a try/catch to avoid breaking the loop for a wrong listener
        try {
            $this->events->dispatch(new $className($data));
        } catch (Throwable $e) {
            $this->dispatchErrorsEmitter->emit([$eventName, $data, $e]);

            Log::warning(sprintf(
                '[TwitchPubSub] Listener for event "%s" threw an error: %s',
                $eventName,
                $e->getMessage()
            ));

            return false;
        }

        return true;
    }

    /**
     * Get subscription from the user args.
     *
     * @param  string|array $twitchAuthToken - Token or array of tokens with their topics
     * @param  array $topics
     * @return array|null
     */
    protected function getSubscriptionsData($twitchAuthToken, array $topics = []): ?array
    {
        $subscriptions = is_array($twitchAuthToken) ? $twitchAuthToken : [
            $twitchAuthToken => $topics,
        ];

        // Validate subscriptions array
        if (! Collection::make($subscriptions)->every(fn ($value) => is_array($value))) {
            return null;
        }

        return $subscriptions;
    }
}
