<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

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

    public function testGetBinsForNotificationMatchesCyclesCorrectly(): void
    {
        // Bins we expect to see
        DB::table('bins')->insert([
            [
                'id' => '1',
                'house_id' => '1',
                'cycle' => 'weekly',
                'day_of_week' => 'saturday',
                'week_of_month' => null,
                'start_date' => null,
            ],
            [
                'id' => '2',
                'house_id' => '1',
                'cycle' => 'fortnightly',
                'day_of_week' => 'saturday',
                'week_of_month' => null,
                'start_date' => '1985-10-26',
            ],
            [
                'id' => '3',
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
                'house_id' => '1',
                'cycle' => 'weekly',
                'day_of_week' => 'tuesday', // Wrong day_of_week
                'week_of_month' => null,
                'start_date' => null,
            ],
            [
                'house_id' => '1',
                'cycle' => 'fortnightly',
                'day_of_week' => 'saturday',
                'week_of_month' => null,
                'start_date' => '1985-10-19', // Wrong week of fortnight
            ],
            [
                'house_id' => '1',
                'cycle' => 'monthly',
                'day_of_week' => 'saturday',
                'week_of_month' => 1, // Wrong week_of_month
                'start_date' => null,
            ],
            [
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

    public function testGetBinsForNotificationHandlesOffsetDays(): void
    {
        // Positive: weekly that matches the shifted day
        DB::table('bins')->insert([
            'house_id' => '1',
            'cycle' => 'weekly',
            'day_of_week' => 'monday',
            'week_of_month' => null,
            'start_date' => null,
        ]);

        DB::table('bins')->insert([
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
        self::assertSame('weekly', $bin->getAttribute('cycle'));
        self::assertSame('monday', $bin->getAttribute('day_of_week'));
    }

    private function migrate(): void
    {
        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('house_id');
            $table->string('cycle');
            $table->string('day_of_week');
            $table->unsignedTinyInteger('week_of_month')->nullable();
            $table->date('start_date')->nullable();
            $table->timestamps();
        });
    }
}
