<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
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
        DB::table('users')->insert(['id' => '1', 'external_id' => 'user_111']);

        DB::table('houses')->insert([
            ['id' => '1', 'timezone' => 'Australia/Melbourne'],
            ['id' => '2', 'timezone' => 'America/Los_Angeles'],
        ]);

        DB::table('notifications')->insert([
            // Melbourne
            ['id' => '3', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04aaaaaaaa', 'send_time' => '11:21'],
            ['id' => '4', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04cccccccc', 'send_time' => '11:20'],

            // Los Angeles
            ['id' => '5', 'user_id' => '1', 'house_id' => '2', 'type' => 'sms', 'target' => '04dddddddd', 'send_time' => '18:21'],
            ['id' => '6', 'user_id' => '1', 'house_id' => '2', 'type' => 'sms', 'target' => '04eeeeeeee', 'send_time' => '19:21'],
        ]);

        $results = new NotificationRepository()
            ->getDueNotifications(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC')));

        self::assertCount(2, $results);
        self::assertInstanceOf(Notification::class, $results->first());
        self::assertSame(['3', '5'], $results->pluck('id')->all());
        self::assertSame(2, $results->count());
        self::assertSame('user_111', $results->first()->userExternalId);
    }

    public function testGetNotificationsForUser(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04aaaaaaaa', 'send_time' => '00:00'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04bbbbbbbb', 'send_time' => '00:00'],
            ['id' => '3', 'user_id' => '2', 'house_id' => '1', 'type' => 'sms', 'target' => '04cccccccc', 'send_time' => '00:00'],
        ]);

        $results = new NotificationRepository()->getNotificationsForUser(new AuthenticatedUser(['sub' => 'user_111']));

        self::assertCount(2, $results);
        self::assertInstanceOf(Notification::class, $results->first());
        self::assertEquals(['1', '2'], $results->pluck('id')->all());
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('timezone');
        });

        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('house_id');
            $table->string('type');
            $table->string('target');
            $table->integer('offset_days')->default(0);
            $table->string('send_time');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
}
