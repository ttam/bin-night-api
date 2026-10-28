<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

final class HousesTest extends ApiTestCase
{
    public function testCreateAllowsANameAnotherUserHas(): void
    {
        $this->createHouse('Hill Valley', '1955-11-12 06:00:00', $this->createUser('user_222'));

        $this->postJson('/api/v1/houses', $this->validPayload())->assertCreated();
    }

    public function testCreateCountsTheNewHouseAsActive(): void
    {
        $this->useLimits(houses: 1);

        $this->postJson('/api/v1/houses', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Hill Valley');

        self::assertSame(1, DB::table('houses')->count());
    }

    public function testCreateOverThePlanLimitIsRejected(): void
    {
        $this->useLimits(houses: 1);
        $this->createHouse('Lyon Estates', '1955-11-12 06:00:00');

        $this->postJson('/api/v1/houses', $this->validPayload())
            ->assertForbidden()
            ->assertExactJson(['code' => 'plan-limit-reached', 'message' => 'Your plan allows 1 house.']);

        self::assertSame(1, DB::table('houses')->count());
    }

    public function testCreateRejectsANameTheUserAlreadyHas(): void
    {
        $this->useLimits(houses: 2);
        $this->createHouse('Hill Valley', '1955-11-12 06:00:00');

        $this->postJson('/api/v1/houses', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        self::assertSame(1, DB::table('houses')->count());
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $this->postJson('/api/v1/houses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bin_night', 'name', 'timezone']);
    }

    public function testDeleteFreesTheNameButRecordsIt(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->deleteJson('/api/v1/houses/' . $houseId)->assertNoContent();

        self::assertSame($houseId . '-Hill Valley', DB::table('houses')->where('id', $houseId)->value('name'));
        $this->postJson('/api/v1/houses', $this->validPayload())->assertCreated()->assertJsonPath('data.name', 'Hill Valley');
    }

    public function testDeleteLetsAnOverLimitUserDeleteAnInactiveHouse(): void
    {
        $this->useLimits(houses: 1);
        $this->createHouse('Older', '1955-11-12 06:00:00');
        $newerId = $this->createHouse('Newer', '2015-10-21 16:29:00');

        $this->deleteJson('/api/v1/houses/' . $newerId)->assertNoContent();

        self::assertNotNull(DB::table('houses')->where('id', $newerId)->value('deleted_at'));
        $this->getJson('/api/v1/houses')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Older');
    }

    public function testDeleteOfAnotherUsersHouseIsNotFound(): void
    {
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $this->createUser('user_222'));

        $this->deleteJson('/api/v1/houses/' . $houseId)->assertNotFound();

        self::assertNull(DB::table('houses')->where('id', $houseId)->value('deleted_at'));
    }

    public function testDeleteSoftDeletesTheHouseWithItsBinsAndNotifications(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1985-10-26 01:21:00', name: 'Morning text');
        $otherHouseId = $this->createHouse('Twin Pines', '1985-10-26 01:22:00');

        $this->deleteJson('/api/v1/houses/' . $houseId)->assertNoContent();

        self::assertNotNull(DB::table('houses')->where('id', $houseId)->value('deleted_at'));
        self::assertNull(DB::table('houses')->where('id', $otherHouseId)->value('deleted_at'));
        self::assertSame($binId . '-Rubbish', DB::table('bins')->whereNotNull('deleted_at')->value('name'));
        self::assertSame($notificationId . '-Morning text', DB::table('notifications')->whereNotNull('deleted_at')->value('name'));
    }

    public function testListMarksHousesOverThePlanLimitInactive(): void
    {
        $this->useLimits(houses: 1);
        $this->createHouse('Newer', '2015-10-21 16:29:00');
        $this->createHouse('Older', '1955-11-12 06:00:00');

        $response = $this->getJson('/api/v1/houses')->assertOk();

        $houses = \collect($response->json('data'))->keyBy('name');
        self::assertTrue($houses['Older']['active']);
        self::assertFalse($houses['Newer']['active']);
    }

    public function testUpdateChangesTheHouse(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->patchJson('/api/v1/houses/' . $houseId, ['name' => 'Twin Pines'])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Twin Pines');

        self::assertSame('Twin Pines', DB::table('houses')->where('id', $houseId)->value('name'));
    }

    public function testUpdateOfAnotherUsersHouseIsNotFound(): void
    {
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $this->createUser('user_222'));

        $this->patchJson('/api/v1/houses/' . $houseId, ['name' => 'Twin Pines'])->assertNotFound();

        self::assertSame('Lyon Estates', DB::table('houses')->where('id', $houseId)->value('name'));
    }

    public function testUpdateRejectsANameTheUserAlreadyHasOnAnotherHouse(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createHouse('Twin Pines', '1985-10-26 01:22:00');

        $this->patchJson('/api/v1/houses/' . $houseId, ['name' => 'Twin Pines'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->patchJson('/api/v1/houses/' . $houseId, ['name' => 'Hill Valley'])->assertOk();
    }

    private function validPayload(): array
    {
        return ['bin_night' => 'monday', 'name' => 'Hill Valley', 'timezone' => 'America/Los_Angeles'];
    }
}
