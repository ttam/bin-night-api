<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

final class NotificationsTest extends ApiTestCase
{
    public function testCreateCountsTheNewNotificationAsActive(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.send_time', '18:30')
            ->assertJsonPath('data.target', '+61400000000')
            ->assertJsonPath('data.type', 'sms');

        self::assertSame(1, DB::table('notifications')->count());
    }

    public function testCreateInAnotherUsersHouseIsNotFound(): void
    {
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $this->createUser('user_222'));

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertNotFound();

        self::assertSame(0, DB::table('notifications')->count());
    }

    public function testCreateOverThePlanLimitIsRejected(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000001', '1955-11-12 06:00:00');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))
            ->assertForbidden()
            ->assertExactJson(['code' => 'plan-limit-reached', 'message' => 'Your plan allows 1 notification per house.']);

        self::assertSame(1, DB::table('notifications')->count());
    }

    public function testCreateRejectsAnInvalidPhoneNumberWithAHelpfulMessage(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'target' => '0400000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target' => 'The target must be an international phone number, like +61400000000.']);

        self::assertSame(0, DB::table('notifications')->count());
    }

    public function testCreateRejectsAnUnknownType(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'type' => 'carrier-pigeon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', ['house_id' => $houseId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_days', 'send_time', 'target', 'type']);
    }

    public function testCreateWithoutAHouseIsNotFound(): void
    {
        $this->postJson('/api/v1/notifications', [])->assertNotFound();
    }

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

    public function testUpdateChangesTheNotification(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 3, 'send_time' => '07:00'])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.lead_days', 3)
            ->assertJsonPath('data.send_time', '07:00')
            ->assertJsonPath('data.target', '+61400000000');

        self::assertSame(3, DB::table('notifications')->where('id', $notificationId)->value('lead_days'));
    }

    public function testUpdateChecksTheTargetAgainstTheStoredType(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['target' => '0400000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target' => 'The target must be an international phone number, like +61400000000.']);

        self::assertSame('+61400000000', DB::table('notifications')->where('id', $notificationId)->value('target'));
    }

    public function testUpdateIgnoresAHouseId(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $otherHouseId = $this->createHouse('Twin Pines', '2015-10-21 16:29:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['house_id' => $otherHouseId, 'lead_days' => 2])
            ->assertOk();

        self::assertSame($houseId, DB::table('notifications')->where('id', $notificationId)->value('house_id'));
    }

    public function testUpdateKeepsAnOverLimitNotificationEditableAndInactive(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        $notificationId = $this->createNotification($houseId, '+61400000001', '2015-10-21 16:29:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['type' => 'sms', 'lead_days' => 2])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.lead_days', 2);
    }

    public function testUpdateOfAnotherUsersNotificationIsNotFound(): void
    {
        $otherUserId = $this->createUser('user_222');
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId);
        $notificationId = $this->createNotification($houseId, '+61400000002', '1955-11-12 06:00:00', $otherUserId);

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 2])->assertNotFound();

        self::assertSame(1, DB::table('notifications')->where('id', $notificationId)->value('lead_days'));
    }

    public function testUpdateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 8, 'send_time' => '11:21', 'type' => 'carrier-pigeon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_days', 'send_time', 'type']);
    }

    private function validPayload(string $houseId): array
    {
        return [
            'house_id' => $houseId,
            'lead_days' => 1,
            'send_time' => '18:30',
            'target' => '+61400000000',
            'type' => 'sms',
        ];
    }
}
