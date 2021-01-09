<?php

namespace Danilopolani\TwitchPubSub\Tests;

use Danilopolani\TwitchPubSub\TwitchPubSub;

class GetEventNameTest extends TestCase
{
    /**
     * @test
     * @dataProvider eventsDataProvider
     */
    public function getEventName_with_existing_topic(string $topic, string $event)
    {
        /** @var TwitchPubSub $instance */
        $instance = $this->app->make(TwitchPubSub::class);

        $this->assertSame($event, $this->callMethod($instance, 'getEventName', [$topic]));
    }

    /** @test */
    public function getEventName_with_not_found_topic()
    {
        /** @var TwitchPubSub $instance */
        $instance = $this->app->make(TwitchPubSub::class);

        $this->assertNull($this->callMethod($instance, 'getEventName', ['foo']));
    }

    public function eventsDataProvider(): array
    {
        return [
            ['channel-bits-events-v1.44322889', 'BitsDonated'],
            ['channel-bits-events-v2.44322889', 'BitsDonated'],
            ['channel-bits-badge-unlocks.44322889', 'BitsBadgeUnlocked'],
            ['channel-points-channel-v1.44322889', 'RewardRedeemed'],
            ['channel-subscribe-events-v1.44322889', 'SubscriptionReceived'],
            ['chat_moderator_actions.46024993.44322889', 'ModeratorActionSent'],
            ['whispers.44322889', 'WhisperReceived'],
        ];
    }
}
