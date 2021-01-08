<?php

namespace Danilopolani\TwitchPubSub;

use Amp\Loop;
use Amp\Websocket\Client;
use Amp\Websocket\Client\Handshake;
use Amp\Websocket\ClosedException;
use Amp\Websocket\Options;
use Exception;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TwitchPubSub
{
    protected EventsDispatcher $events;

    /**
     * Class constructor.
     *
     * @var EventsDispatcher $events
     */
    public function __construct(EventsDispatcher $events)
    {
        $this->events = $events;
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
        if (!$subscriptions = $this->getSubscriptionsData($twitchAuthToken, $topics)) {
            throw new Exception('Subscriptions array is not valid. It must be an associative array with Auth Token (key) and topics (value).');
        }

        Loop::run(function () use ($subscriptions) {
            // Connect
            $options = Options::createClientDefault()->withHeartbeatPeriod(60);
            $handshake = new Handshake('wss://pubsub-edge.twitch.tv', $options);

            $connection = yield Client\connect($handshake);

            // Handle SIGINT to unlisten
            Loop::onSignal(SIGINT, function () use ($subscriptions, $connection) {
                foreach ($subscriptions as $token => $topics) {
                    yield $connection->send(json_encode([
                        'type' => 'UNLISTEN',
                        'data' => [
                            'topics' => $topics,
                            'auth_token' => $token,
                        ],
                    ]));
                }

                yield Loop::stop();
            });

            // Send listen request
            foreach ($subscriptions as $token => $topics) {
                yield $connection->send(json_encode([
                    'type' => 'LISTEN',
                    'data' => [
                        'topics' => $topics,
                        'auth_token' => $token,
                    ],
                ]));
            }

            try {
                /** @var \Amp\Websocket\Message $message */
                while ($message = yield $connection->receive()) {
                    $payload = json_decode(yield $message->buffer(), true);

                    // Stop if cannot decode payload
                    if (is_null($payload)) {
                        continue;
                    }

                    // Handle RECONNECT
                    if ($payload['type'] === 'RECONNECT') {
                        $connection = yield Client\connect($handshake);

                        continue;
                    }

                    $this->handleMessage($payload);
                }
            } catch (ClosedException $e) {
                Log::debug(sprintf('[TwitchPubSub] Connection closed: %s. Reconnecting...', $e->getMessage()));
            }
        });
    }

    /**
     *  Handle a message.
     *
     * @param  array $payload
     * @return void
     */
    protected function handleMessage(array $payload): void
    {
        // Skip response, heartbeat etc. and if there's no topic key
        if ($payload['type'] !== 'MESSAGE' || !$topic = ($payload['data']['topic'] ?? null)) {
            return;
        }

        $message = json_decode($payload['data']['message'], true);

        if (isset($message['data'])) {
            $message['data'] = json_decode($message['data'], true);
        }

        // Skip threads for whispers
        if ($message['type'] === 'thread') {
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
     * @return void
     */
    protected function dispatchEvent(string $topic, array $data): void
    {
        if (!$eventName = $this->getEventName($topic)) {
            Log::warning('[TwitchPubSub] Event name not found for topic "' . $topic . '"');

            return;
        }

        $className = '\Danilopolani\TwitchPubSub\Events\\' . $eventName;
        if (!class_exists($className)) {
            Log::warning('[TwitchPubSub] Event class not found for event "' . $eventName . '"');

            return;
        }

        // Wrap dispatcher inside a try/catch to avoid breaking the loop for a wrong listener
        try {
            $this->events->dispatch(new $className($data));
        } catch (Throwable $e) {
            Log::warning(sprintf(
                '[TwitchPubSub] Listener for event "%s" threw an error: %s',
                $eventName,
                $e->getMessage()
            ));
        }
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
        if (!Collection::make($subscriptions)->every(fn ($value) => is_array($value))) {
            return null;
        }

        return $subscriptions;
    }
}