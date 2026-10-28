<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\Plan;
use App\Models\Bin;
use App\Models\House;
use App\Models\User;
use App\Policies\BinPolicy;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class BinPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testCreateDoesNotCountBinsInOtherHouses(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h2'],
            ['id' => 'b2', 'house_id' => 'h2'],
            ['id' => 'b3', 'house_id' => 'h1'],
        ]);

        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy($this->planRepository(binsPerHouse: 2))->create($user, $house)->allowed());
    }

    public function testCreateFailsAtTheLimitOfOne(): void
    {
        DB::table('bins')->insert(['id' => 'b1', 'house_id' => 'h1']);

        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        $response = new BinPolicy($this->planRepository(binsPerHouse: 1))->create($user, $house);

        self::assertFalse($response->allowed());
        self::assertSame('Your plan allows 1 bin per house.', $response->message());
    }

    public function testCreateFailsAtThePlanLimit(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h1'],
            ['id' => 'b2', 'house_id' => 'h1'],
            ['id' => 'b3', 'house_id' => 'h1'],
        ]);

        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        $response = new BinPolicy($this->planRepository(binsPerHouse: 3))->create($user, $house);

        self::assertFalse($response->allowed());
        self::assertSame('Your plan allows 3 bins per house.', $response->message());
    }

    public function testCreateFailsWithOtherUsersHouse(): void
    {
        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '2');
        $user = new User()->setAttribute('id', '1');

        $response = new BinPolicy()->create($user, $house);

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testCreateFailsWithUnknownHouse(): void
    {
        $response = new BinPolicy()->create(new User()->setAttribute('id', '1'), null);

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testCreateLooksUpTheLimitsForTheUsersPlan(): void
    {
        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1')->setAttribute('plan', Plan::PLUS->value);

        $repository = $this->createMock(PlanRepositoryInterface::class);
        $repository->expects($this->once())->method('getLimits')->with(Plan::PLUS)->willReturn(new PlanLimits(1, 1, 1, []));

        new BinPolicy($repository)->create($user, $house);
    }

    public function testCreatePassesWithAnUnlimitedPlan(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h1'],
            ['id' => 'b2', 'house_id' => 'h1'],
        ]);

        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy($this->planRepository(binsPerHouse: PlanLimits::UNLIMITED))->create($user, $house)->allowed());
    }

    public function testCreatePassesWithUserOwnedHouse(): void
    {
        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy($this->planRepository(binsPerHouse: 1))->create($user, $house)->allowed());
    }

    public function testDeleteFailsWithOtherUsersBin(): void
    {
        $bin = new Bin()->setAttribute('user_id', '2');
        $user = new User()->setAttribute('id', '1');

        $response = new BinPolicy()->delete($user, $bin);

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testDeletePassesWithUserOwnedBin(): void
    {
        $bin = new Bin()->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy()->delete($user, $bin)->allowed());
    }

    public function testDeletePassesWithUserOwnedBinEvenWhenOverThePlanLimit(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h1'],
            ['id' => 'b2', 'house_id' => 'h1'],
        ]);

        $bin = new Bin()->setAttribute('house_id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy($this->planRepository(binsPerHouse: 1))->delete($user, $bin)->allowed());
    }

    public function testUpdateFailsWithOtherUsersBin(): void
    {
        $bin = new Bin()->setAttribute('user_id', '2');
        $user = new User()->setAttribute('id', '1');

        $response = new BinPolicy()->update($user, $bin);

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testUpdatePassesWithUserOwnedBin(): void
    {
        $bin = new Bin()->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy()->update($user, $bin)->allowed());
    }

    private function migrate(): void
    {
        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('house_id');
            $table->softDeletes();
        });
    }

    private function planRepository(int $binsPerHouse): PlanRepositoryInterface
    {
        $repository = $this->createStub(PlanRepositoryInterface::class);
        $repository->method('getLimits')->willReturn(new PlanLimits(1, $binsPerHouse, 1, []));

        return $repository;
    }
}
