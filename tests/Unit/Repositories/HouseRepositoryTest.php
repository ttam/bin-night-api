<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\House\CreateHouse;
use App\DTO\House\UpdateHouse;
use App\Enums\DayOfWeek;
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

    public function testCreateHouse(): void
    {
        DB::table('users')->insert([['id' => '1', 'external_id' => 'user_111']]);

        $house = new HouseRepository()->createHouse(
            new AuthenticatedUser(['sub' => 'user_111']),
            new CreateHouse(DayOfWeek::MONDAY, 'Example', new \DateTimeZone('America/Los_Angeles')),
        );

        self::assertSame('monday', $house->binNight);
        self::assertSame('Example', $house->name);
        self::assertSame('1', $house->userId);
        self::assertSame('America/Los_Angeles', $house->timezone->getName());
    }

    public function testDeleteHouse(): void
    {
        DB::table('houses')->insert([
            ['id' => '1', 'user_id' => '1', 'name' => 'Example'],
            ['id' => '2', 'user_id' => '1', 'name' => 'Keep'],
        ]);
        DB::table('bins')->insert([
            ['id' => '1', 'house_id' => '1', 'name' => 'Rubbish'],
            ['id' => '2', 'house_id' => '2', 'name' => 'Rubbish'],
        ]);
        DB::table('notifications')->insert([
            ['id' => '1', 'house_id' => '1', 'name' => 'Morning text'],
            ['id' => '2', 'house_id' => '2', 'name' => 'Morning text'],
        ]);

        new HouseRepository()->deleteHouse(new House()->newQuery()->findOrFail('1'));

        self::assertSame(['2'], new House()->newQuery()->pluck('id')->all());
        self::assertNotNull(DB::table('houses')->where('id', '1')->value('deleted_at'));
        self::assertSame('1-Example', DB::table('houses')->where('id', '1')->value('name'));
        self::assertSame('Keep', DB::table('houses')->where('id', '2')->value('name'));
        self::assertNull(DB::table('houses')->where('id', '2')->value('deleted_at'));
        self::assertNotNull(DB::table('bins')->where('id', '1')->value('deleted_at'));
        self::assertSame('1-Rubbish', DB::table('bins')->where('id', '1')->value('name'));
        self::assertSame('Rubbish', DB::table('bins')->where('id', '2')->value('name'));
        self::assertNull(DB::table('bins')->where('id', '2')->value('deleted_at'));
        self::assertNotNull(DB::table('notifications')->where('id', '1')->value('deleted_at'));
        self::assertSame('1-Morning text', DB::table('notifications')->where('id', '1')->value('name'));
        self::assertSame('Morning text', DB::table('notifications')->where('id', '2')->value('name'));
        self::assertNull(DB::table('notifications')->where('id', '2')->value('deleted_at'));
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

    public function testUpdateHouse(): void
    {
        DB::table('users')->insert([['id' => 1, 'external_id' => 'user_111']]);
        DB::table('houses')->insert([['id' => '1', 'user_id' => '1']]);

        $result = new HouseRepository()->updateHouse(
            new House()->newQuery()->first(),
            new UpdateHouse(['name' => 'Example']),
        );

        self::assertSame('Example', $result->name);
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('user_id');
            $table->string('bin_night')->nullable();
            $table->string('name')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });

        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('house_id');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('house_id');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
