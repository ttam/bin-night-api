<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Notification;
use App\Repositories\NotificationRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class NotificationRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootConnection()->bootEloquent()->migrate();
    }

    public function testGetDueNotificationsFindsRecordsForMultipleTimeZones(): void
    {
        DB::table('houses')->insert([
            ['id' => '1', 'timezone' => 'Australia/Melbourne'],
            ['id' => '2', 'timezone' => 'America/Los_Angeles'],
        ]);

        DB::table('notifications')->insert([
            // Melbourne
            ['id' => '3', 'house_id' => '1', 'channel' => 'sms', 'target' => '04aaaaaaaa', 'send_time' => '11:21'],
            ['id' => '4', 'house_id' => '1', 'channel' => 'sms', 'target' => '04cccccccc', 'send_time' => '11:20'],

            // Los Angeles
            ['id' => '5', 'house_id' => '2', 'channel' => 'sms', 'target' => '04dddddddd', 'send_time' => '18:21'],
            ['id' => '6', 'house_id' => '2', 'channel' => 'sms', 'target' => '04eeeeeeee', 'send_time' => '19:21'],
        ]);

        $results = new NotificationRepository()
            ->getDueNotifications(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC')));

        self::assertCount(2, $results);
        self::assertInstanceOf(Notification::class, $results->first());
        self::assertSame(['3', '5'], $results->pluck('id')->all());
        self::assertSame(2, $results->count());
    }

    private function migrate(): void
    {
        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('timezone');
        });

        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('house_id');
            $table->string('channel');
            $table->string('target');
            $table->integer('offset_days')->default(0);
            $table->string('send_time');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
}
