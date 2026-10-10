<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

final class HousesTest extends ApiTestCase
{
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

    public function testCreateRejectsInvalidInput(): void
    {
        $this->postJson('/api/v1/houses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bin_night', 'name', 'timezone']);
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

    private function validPayload(): array
    {
        return ['bin_night' => 'monday', 'name' => 'Hill Valley', 'timezone' => 'America/Los_Angeles'];
    }
}
