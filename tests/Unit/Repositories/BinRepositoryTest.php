<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\DTO\Bin\UpdateBin;
use App\Enums\Cycle;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Repositories\BinRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class BinRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testCreateBin(): void
    {
        DB::table('users')->insert([['id' => '1', 'external_id' => 'user_111']]);

        $bin = new BinRepository()->createBin(
            new AuthenticatedUser(['sub' => 'user_111']),
            new CreateBin('000000', Cycle::FORTNIGHTLY, 'house-1', 'ffffff', 'Example'),
        );

        self::assertSame('Example', $bin->name);
        self::assertSame(Cycle::FORTNIGHTLY, $bin->cycle);
        self::assertSame('000000', $bin->binColor);
        self::assertSame('ffffff', $bin->lidColor);
        self::assertSame('1', $bin->userId);
        self::assertSame('house-1', $bin->getAttribute('house_id'));
        self::assertStringStartsWith(\gmdate('Y-m-d'), DB::table('bins')->value('start_date'));
        self::assertSame('000000', DB::table('bins')->value('bin_color'));
    }

    public function testDeleteBin(): void
    {
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'name' => 'Rubbish'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'name' => 'Keep'],
        ]);

        new BinRepository()->deleteBin(new Bin()->newQuery()->findOrFail('1'));

        self::assertSame(['2'], new Bin()->newQuery()->pluck('id')->all());
        self::assertNotNull(DB::table('bins')->where('id', '1')->value('deleted_at'));
        self::assertSame('1-Rubbish', DB::table('bins')->where('id', '1')->value('name'));
        self::assertSame('Keep', DB::table('bins')->where('id', '2')->value('name'));
    }

    public function testDeleteBinKeepsTheRenamedNameWithinTheColumnLength(): void
    {
        $id = '11111111-2222-3333-4444-555555555555';
        DB::table('bins')->insert([
            ['id' => $id, 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'name' => \str_repeat('é', 255)],
        ]);

        new BinRepository()->deleteBin(new Bin()->newQuery()->findOrFail($id));

        $name = DB::table('bins')->where('id', $id)->value('name');
        self::assertSame(255, \mb_strlen($name));
        self::assertSame($id . '-' . \str_repeat('é', 218), $name);
    }

    public function testGetBinsForNotificationHandlesOffsetDays(): void
    {
        // Positive: weekly that matches the shifted day
        DB::table('bins')->insert([
            'user_id' => '1',
            'house_id' => '1',
            'cycle' => 'weekly',
            'day_of_week' => 'monday',
            'week_of_month' => null,
            'start_date' => null,
        ]);

        DB::table('bins')->insert([
            'user_id' => '1',
            'house_id' => '1',
            'cycle' => 'weekly',
            'day_of_week' => 'saturday',
            'week_of_month' => null,
            'start_date' => null,
        ]);

        $house = new House()->setAttribute('id', '1')->setAttribute('timezone', 'Australia/Melbourne');
        $notification = new Notification()->house()->associate($house)->setAttribute('lead_days', 2);

        $now = new DateTimeImmutable('1985-10-26 01:21:00', new DateTimeZone('UTC'));
        $result = new BinRepository()->getBinsForNotification($notification, $now);
        $bin = $result->first();

        self::assertCount(1, $result);
        self::assertInstanceOf(Bin::class, $bin);
        self::assertSame('weekly', $bin->getAttribute('cycle')->value);
        self::assertSame('monday', $bin->getAttribute('day_of_week'));
    }

    public function testGetBinsForNotificationMatchesCyclesCorrectly(): void
    {
        // Bins we expect to see
        DB::table('bins')->insert([
            [
                'id' => '1',
                'user_id' => '1',
                'house_id' => '1',
                'cycle' => 'weekly',
                'day_of_week' => 'saturday',
                'week_of_month' => null,
                'start_date' => null,
            ],
            [
                'id' => '2',
                'user_id' => '1',
                'house_id' => '1',
                'cycle' => 'fortnightly',
                'day_of_week' => 'saturday',
                'week_of_month' => null,
                'start_date' => '1985-10-26',
            ],
            [
                'id' => '3',
                'user_id' => '1',
                'house_id' => '1',
                'cycle' => 'monthly',
                'day_of_week' => 'saturday',
                'week_of_month' => 4,
                'start_date' => null,
            ],
        ]);

        // Bins we don't want to see
        DB::table('bins')->insert([
            [
                'user_id' => '1',
                'house_id' => '1',
                'cycle' => 'weekly',
                'day_of_week' => 'tuesday', // Wrong day_of_week
                'week_of_month' => null,
                'start_date' => null,
            ],
            [
                'user_id' => '1',
                'house_id' => '1',
                'cycle' => 'fortnightly',
                'day_of_week' => 'saturday',
                'week_of_month' => null,
                'start_date' => '1985-10-19', // Wrong week of fortnight
            ],
            [
                'user_id' => '1',
                'house_id' => '1',
                'cycle' => 'monthly',
                'day_of_week' => 'saturday',
                'week_of_month' => 1, // Wrong week_of_month
                'start_date' => null,
            ],
            [
                'user_id' => '1',
                'house_id' => '2', // Wrong house
                'cycle' => 'weekly',
                'day_of_week' => 'saturday',
                'week_of_month' => null,
                'start_date' => null,
            ],
        ]);

        $now = new DateTimeImmutable('1985-10-26 01:21:00', new DateTimeZone('UTC'));
        $house = new House()->setAttribute('id', '1')->setAttribute('timezone', 'Australia/Melbourne');
        $notification = new Notification()->house()->associate($house);

        $results = new BinRepository()->getBinsForNotification($notification, $now);

        self::assertCount(3, $results);
        self::assertInstanceOf(Bin::class, $results->first());
        self::assertEquals(['1', '2', '3'], $results->pluck('id')->all());
    }

    public function testGetBinsForUser(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'day_of_week' => 'saturday'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'day_of_week' => 'saturday'],
            ['id' => '3', 'user_id' => '2', 'house_id' => '1', 'cycle' => 'weekly', 'day_of_week' => 'saturday'],
        ]);

        $results = new BinRepository()->getBinsForUser(new AuthenticatedUser(['sub' => 'user_111']));

        self::assertCount(2, $results);
        self::assertInstanceOf(Bin::class, $results->first());
        self::assertEquals(['1', '2'], $results->pluck('id')->all());
    }

    public function testUpdateBin(): void
    {
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'day_of_week' => 'saturday', 'name' => 'Old'],
        ]);

        $result = new BinRepository()->updateBin(
            new Bin()->newQuery()->first(),
            new UpdateBin(['bin_color' => '00dd00', 'name' => 'Example']),
        );

        self::assertSame('Example', $result->name);
        self::assertSame('00dd00', $result->binColor);
        self::assertSame('Example', DB::table('bins')->value('name'));
        self::assertSame('00dd00', DB::table('bins')->value('bin_color'));
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
        });

        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->string('id')->nullable();
            $table->string('house_id');
            $table->string('user_id');
            $table->string('cycle');
            $table->string('name')->nullable();
            $table->string('bin_color')->nullable();
            $table->string('lid_color')->nullable();
            $table->string('day_of_week')->nullable();
            $table->unsignedTinyInteger('week_of_month')->nullable();
            $table->date('start_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
