<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

final class BinsTest extends ApiTestCase
{
    public function testCreateCountsTheNewBinAsActive(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/bins', $this->validPayload($houseId))
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Rubbish');

        self::assertSame(1, DB::table('bins')->count());
    }

    public function testCreateInAnotherUsersHouseIsNotFound(): void
    {
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $this->createUser('user_222'));

        $this->postJson('/api/v1/bins', $this->validPayload($houseId))->assertNotFound();

        self::assertSame(0, DB::table('bins')->count());
    }

    public function testCreateOverThePlanLimitIsRejected(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Recycling', '1955-11-12 06:00:00');

        $this->postJson('/api/v1/bins', $this->validPayload($houseId))
            ->assertForbidden()
            ->assertExactJson(['code' => 'plan-limit-reached', 'message' => 'Your plan allows 1 bin per house.']);

        self::assertSame(1, DB::table('bins')->count());
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/bins', ['house_id' => $houseId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bin_color', 'cycle', 'lid_color', 'name']);
    }

    public function testCreateWithoutAHouseIsNotFound(): void
    {
        $this->postJson('/api/v1/bins', [])->assertNotFound();
    }

    public function testListMarksBinsOverThePlanLimitInactive(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Newer', '2015-10-21 16:29:00');
        $this->createBin($houseId, 'Older', '1955-11-12 06:00:00');

        $response = $this->getJson('/api/v1/bins')->assertOk();

        $bins = \collect($response->json('data'))->keyBy('name');
        self::assertTrue($bins['Older']['active']);
        self::assertNotNull($bins['Older']['next_bin_night']);
        self::assertFalse($bins['Newer']['active']);
        self::assertNull($bins['Newer']['next_bin_night']);
    }

    public function testRequiresAuthentication(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $this->getJson('/api/v1/bins')->assertUnauthorized();
    }

    public function testUpdateChangesTheBin(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/bins/' . $binId, ['name' => 'Garden'])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Garden');

        self::assertSame('Garden', DB::table('bins')->where('id', $binId)->value('name'));
    }

    public function testUpdateKeepsAnOverLimitBinEditableAndInactive(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Older', '1955-11-12 06:00:00');
        $binId = $this->createBin($houseId, 'Newer', '2015-10-21 16:29:00');

        $this->patchJson('/api/v1/bins/' . $binId, ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.next_bin_night', null);
    }

    public function testUpdateOfAnotherUsersBinIsNotFound(): void
    {
        $otherUserId = $this->createUser('user_222');
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId);
        $binId = $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00', $otherUserId);

        $this->patchJson('/api/v1/bins/' . $binId, ['name' => 'Garden'])->assertNotFound();

        self::assertSame('Rubbish', DB::table('bins')->where('id', $binId)->value('name'));
    }

    private function validPayload(string $houseId): array
    {
        return [
            'bin_color' => '#000000',
            'cycle' => 'weekly',
            'house_id' => $houseId,
            'lid_color' => '#ffffff',
            'name' => 'Rubbish',
        ];
    }
}
