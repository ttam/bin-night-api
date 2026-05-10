<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\Models\House;
use App\Repositories\HouseRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class HouseRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }
    public function testGetHousesForUser(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        DB::table('houses')->insert([
            ['id' => '1', 'user_id' => '1'],
            ['id' => '2', 'user_id' => '1'],
            ['id' => '3', 'user_id' => '2'],
        ]);

        $results = new HouseRepository()->getHousesForUser(new AuthenticatedUser(['sub' => 'user_111']));

        self::assertCount(2, $results);
        self::assertInstanceOf(House::class, $results->first());
        self::assertEquals(['1', '2'], $results->pluck('id')->all());
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('user_id');
        });
    }
}
