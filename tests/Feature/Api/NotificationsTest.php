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
            ->assertJsonPath('data.name', 'Marty\'s Phone')
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

    public function testCreateRejectsANameAlreadyUsedInTheHouse(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000001', '1955-11-12 06:00:00', name: 'Marty\'s Phone');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
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

    public function testCreateRejectsASendTimeOffTheHalfHour(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'send_time' => '18:15'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.send_time.0', 'The send time must be on the hour or half hour, like 18:00 or 18:30.');

        self::assertSame(0, DB::table('notifications')->count());
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', ['house_id' => $houseId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_days', 'name', 'send_time', 'target', 'type']);
    }

    public function testCreateWithoutAHouseIsNotFound(): void
    {
        $this->postJson('/api/v1/notifications', [])->assertNotFound();
    }

    public function testDeleteFreesTheNameButRecordsIt(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00', name: 'Marty\'s Phone');

        $this->deleteJson('/api/v1/notifications/' . $notificationId)->assertNoContent();

        self::assertSame(\sprintf('%s-%s', $notificationId, 'Marty\'s Phone'), DB::table('notifications')->where('id', $notificationId)->value('name'));
        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertCreated()->assertJsonPath('data.name', 'Marty\'s Phone');
    }

    public function testDeleteLetsAnOverLimitUserDeleteAnInactiveNotification(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        $newerId = $this->createNotification($houseId, '+61400000001', '2015-10-21 16:29:00');

        $this->deleteJson('/api/v1/notifications/' . $newerId)->assertNoContent();

        self::assertNotNull(DB::table('notifications')->where('id', $newerId)->value('deleted_at'));
        $this->getJson('/api/v1/notifications')->assertJsonCount(1, 'data');
    }

    public function testDeleteOfAnotherUsersNotificationIsNotFound(): void
    {
        $otherUserId = $this->createUser('user_222');
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId);
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00', $otherUserId);

        $this->deleteJson('/api/v1/notifications/' . $notificationId)->assertNotFound();

        self::assertNull(DB::table('notifications')->where('id', $notificationId)->value('deleted_at'));
    }

    public function testDeleteSoftDeletesTheNotification(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1985-10-26 01:21:00');
        $keptId = $this->createNotification($houseId, '+61400000001', '1985-10-26 01:22:00');

        $this->deleteJson('/api/v1/notifications/' . $notificationId)->assertNoContent();

        self::assertNotNull(DB::table('notifications')->where('id', $notificationId)->value('deleted_at'));
        self::assertNull(DB::table('notifications')->where('id', $keptId)->value('deleted_at'));
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

    public function testUpdateRejectsASendTimeOffTheHalfHour(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['send_time' => '07:45'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.send_time.0', 'The send time must be on the hour or half hour, like 18:00 or 18:30.');

        self::assertSame('18:30', DB::table('notifications')->where('id', $notificationId)->value('send_time'));
    }

    public function testUpdateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 8, 'send_time' => '11:21', 'type' => 'carrier-pigeon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_days', 'send_time', 'type']);
    }

    public function testUpdateRenamesTheNotificationButNotToANameAlreadyInTheHouse(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00', name: 'Marty\'s Phone');
        $this->createNotification($houseId, '+61400000001', '1955-11-12 06:01:00', name: 'Doc\'s Phone');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['name' => 'Doc\'s Phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['name' => 'Marty\'s Phone'])->assertOk();
        $this->patchJson('/api/v1/notifications/' . $notificationId, ['name' => 'Bin text'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bin text');

        self::assertSame('Bin text', DB::table('notifications')->where('id', $notificationId)->value('name'));
    }

    private function validPayload(string $houseId): array
    {
        return [
            'house_id' => $houseId,
            'lead_days' => 1,
            'name' => 'Marty\'s Phone',
            'send_time' => '18:30',
            'target' => '+61400000000',
            'type' => 'sms',
        ];
    }
}
