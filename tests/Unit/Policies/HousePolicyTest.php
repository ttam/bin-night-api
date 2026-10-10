<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\Plan;
use App\Models\House;
use App\Models\User;
use App\Policies\HousePolicy;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class HousePolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testCreatePassesWithNoHouseAttached(): void
    {
        $user = new User();
        self::assertTrue(new HousePolicy()->create($user)->allowed());
    }

    public function testCreateFailsAtThePlanLimit(): void
    {
        DB::table('users')->insert(['id' => '1']);
        DB::table('houses')->insert([['user_id' => '1'], ['user_id' => '1'], ['user_id' => '1']]);

        $response = new HousePolicy($this->planRepository(houses: 3))->create(User::query()->findOrFail('1'));

        self::assertFalse($response->allowed());
        self::assertSame('Your plan allows 3 houses.', $response->message());
    }

    public function testCreateCountsOnlyTheUsersOwnHousesAgainstTheLimit(): void
    {
        DB::table('users')->insert([['id' => '1'], ['id' => '2']]);
        DB::table('houses')->insert([['user_id' => '2'], ['user_id' => '2'], ['user_id' => '1']]);

        $user = User::query()->findOrFail('1');
        self::assertTrue(new HousePolicy($this->planRepository(houses: 2))->create($user)->allowed());

        DB::table('houses')->insert(['user_id' => '1']);

        self::assertFalse(new HousePolicy($this->planRepository(houses: 2))->create($user)->allowed());
    }

    public function testCreateLooksUpTheLimitsForTheUsersPlan(): void
    {
        DB::table('users')->insert(['id' => '1', 'plan' => Plan::PLUS->value]);

        $repository = $this->createMock(PlanRepositoryInterface::class);
        $repository->expects($this->once())->method('getLimits')->with(Plan::PLUS)->willReturn(new PlanLimits(1, 1, 1, []));

        new HousePolicy($repository)->create(User::query()->findOrFail('1'));
    }

    public function testUpdatePassesWithUserOwnedHouse(): void
    {
        $house = new House()->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new HousePolicy()->update($user, $house)->allowed());
    }

    public function testUpdateFailsWithOtherHouse(): void
    {
        $house = new House()->setAttribute('user_id', '2');
        $user = new User()->setAttribute('id', '1');

        self::assertFalse(new HousePolicy()->update($user, $house)->allowed());
    }

    private function planRepository(int $houses): PlanRepositoryInterface
    {
        $repository = $this->createStub(PlanRepositoryInterface::class);
        $repository->method('getLimits')->willReturn(new PlanLimits($houses, 1, 1, []));

        return $repository;
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('plan')->default('free');
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        });
    }
}
