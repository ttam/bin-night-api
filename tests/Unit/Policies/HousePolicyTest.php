<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\House;
use App\Models\User;
use App\Policies\HousePolicy;
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

    public function testCreateFailsWithHouseAttached(): void
    {
        DB::table('users')->insert(['id' => '1']);
        DB::table('houses')->insert(['user_id' => '1']);

        $user = User::query()->findOrFail('1');
        self::assertFalse(new HousePolicy()->create($user)->allowed());
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

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        });
    }
}
