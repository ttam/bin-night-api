<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

final class NotificationsTest extends ApiTestCase
{
    public function testListLeavesOutOtherUsersNotifications(): void
    {
        $otherUserId = $this->createUser('user_222');
        $this->createNotification(
            $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId),
            '+61400000002',
            '1955-11-12 06:00:00',
            $otherUserId,
        );

        $this->getJson('/api/v1/notifications')->assertOk()->assertExactJson(['data' => []]);
    }

    public function testListMarksNotificationsOverThePlanLimitInactive(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000001', '2015-10-21 16:29:00');
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $response = $this->getJson('/api/v1/notifications')->assertOk();

        $notifications = \collect($response->json('data'))->keyBy('target');
        self::assertTrue($notifications['+61400000000']['active']);
        self::assertFalse($notifications['+61400000001']['active']);
    }
}
