<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\UpdateNotification;
use App\Enums\NotificationType;
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
        $this->bootEloquent()->migrate();
    }

    public function testCreateNotification(): void
    {
        DB::table('users')->insert([['id' => '1', 'external_id' => 'user_111']]);

        $notification = new NotificationRepository()->createNotification(
            new AuthenticatedUser(['sub' => 'user_111']),
            new CreateNotification('house-1', 2, 'Marty\'s Phone', '18:30:00', '+61400000000', NotificationType::SMS),
        );

        self::assertSame(2, $notification->leadDays);
        self::assertSame('Marty\'s Phone', $notification->name);
        self::assertSame('18:30:00', $notification->sendTime);
        self::assertSame('+61400000000', $notification->target);
        self::assertSame(NotificationType::SMS, $notification->type);
        self::assertSame('1', $notification->userId);
        self::assertSame('house-1', $notification->getAttribute('house_id'));
        self::assertSame('18:30:00', DB::table('notifications')->value('send_time'));
    }

    public function testDeleteNotification(): void
    {
        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'name' => 'Marty\'s Phone', 'type' => 'sms', 'target' => '+61400000000', 'send_time' => '11:00:00'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'name' => 'Keep', 'type' => 'sms', 'target' => '+61400000001', 'send_time' => '11:00:00'],
        ]);

        new NotificationRepository()->deleteNotification(new Notification()->newQuery()->findOrFail('1'));

        self::assertSame(['2'], new Notification()->newQuery()->pluck('id')->all());
        self::assertNotNull(DB::table('notifications')->where('id', '1')->value('deleted_at'));
        self::assertSame('1-Marty\'s Phone', DB::table('notifications')->where('id', '1')->value('name'));
        self::assertSame('Keep', DB::table('notifications')->where('id', '2')->value('name'));
    }

    public function testGetDueNotificationsAcrossDaylightSaving(): void
    {
        DB::table('users')->insert(['id' => '1', 'external_id' => 'user_111']);
        DB::table('houses')->insert(['id' => '1', 'timezone' => 'Australia/Melbourne']);

        // Melbourne goes from 02:00 to 03:00 at 02:00 on Sunday 4 October 2026, so that night has no 02:00 or 02:30.
        $due = ['user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04aaaaaaaa'];
        DB::table('notifications')->insert([
            ['id' => '0100', 'send_time' => '01:00:00', ...$due],
            ['id' => '0130', 'send_time' => '01:30:00', ...$due],
            ['id' => '0200', 'send_time' => '02:00:00', ...$due],
            ['id' => '0230', 'send_time' => '02:30:00', ...$due],
            ['id' => '0300', 'send_time' => '03:00:00', ...$due],
            ['id' => '0330', 'send_time' => '03:30:00', ...$due],
            ['id' => '0400', 'send_time' => '04:00:00', ...$due],
        ]);

        $repository = new NotificationRepository();
        $ids = static fn (string $slot): array => $repository
            ->getDueNotifications(new DateTimeImmutable($slot, new DateTimeZone('UTC')))
            ->pluck('id')
            ->all();

        self::assertSame(['0100'], $ids('2026-10-03 15:00'));
        self::assertSame(['0130'], $ids('2026-10-03 15:30'));
        self::assertSame(['0300'], $ids('2026-10-03 16:00'));
        self::assertSame(['0330'], $ids('2026-10-03 16:30'));
        self::assertSame(['0400'], $ids('2026-10-03 17:00'));

        // The next night, with daylight saving in force, 15:00 UTC is 02:00.
        self::assertSame(['0200'], $ids('2026-10-04 15:00'));
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
            ['id' => '3', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04aaaaaaaa', 'send_time' => '11:21:00'],
            ['id' => '4', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04cccccccc', 'send_time' => '11:20:00'],

            // Los Angeles
            ['id' => '5', 'user_id' => '1', 'house_id' => '2', 'type' => 'sms', 'target' => '04dddddddd', 'send_time' => '18:21:00'],
            ['id' => '6', 'user_id' => '1', 'house_id' => '2', 'type' => 'sms', 'target' => '04eeeeeeee', 'send_time' => '19:21:00'],
        ]);

        $results = new NotificationRepository()
            ->getDueNotifications(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC')));

        self::assertCount(2, $results);
        self::assertInstanceOf(Notification::class, $results->first());
        self::assertSame(['3', '5'], $results->pluck('id')->all());
        self::assertSame(2, $results->count());
        self::assertSame('user_111', $results->first()->userExternalId);
    }

    public function testGetDueNotificationsIsEmptyWhenThereAreNoHouses(): void
    {
        self::assertCount(0, new NotificationRepository()->getDueNotifications(new DateTimeImmutable('1985-10-26 01:00', new DateTimeZone('UTC'))));
    }

    public function testGetDueNotificationsLeavesOutOnlySlotsThatWereSent(): void
    {
        DB::table('users')->insert(['id' => '1', 'external_id' => 'user_111']);
        DB::table('houses')->insert(['id' => '1', 'timezone' => 'UTC']);

        $due = ['user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04aaaaaaaa', 'send_time' => '01:00:00'];
        DB::table('notifications')->insert([
            ['id' => 'sent', ...$due],
            ['id' => 'failed', ...$due],
            ['id' => 'processing', ...$due],
            ['id' => 'sent-for-another-slot', ...$due],
            ['id' => 'never-tried', ...$due],
        ]);

        DB::table('notification_logs')->insert([
            ['id' => 'l1', 'notification_id' => 'sent', 'scheduled_for' => '1985-10-26 01:00:00', 'status' => 'sent'],
            ['id' => 'l2', 'notification_id' => 'failed', 'scheduled_for' => '1985-10-26 01:00:00', 'status' => 'failed'],
            ['id' => 'l3', 'notification_id' => 'processing', 'scheduled_for' => '1985-10-26 01:00:00', 'status' => 'processing'],
            ['id' => 'l4', 'notification_id' => 'sent-for-another-slot', 'scheduled_for' => '1985-10-25 01:00:00', 'status' => 'sent'],
        ]);

        $results = new NotificationRepository()
            ->getDueNotifications(new DateTimeImmutable('1985-10-26 01:00', new DateTimeZone('UTC')));

        $ids = $results->pluck('id')->all();
        \sort($ids);

        self::assertSame(['failed', 'never-tried', 'processing', 'sent-for-another-slot'], $ids);
    }

    public function testGetNotificationsForUser(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04aaaaaaaa', 'send_time' => '00:00:00'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '04bbbbbbbb', 'send_time' => '00:00:00'],
            ['id' => '3', 'user_id' => '2', 'house_id' => '1', 'type' => 'sms', 'target' => '04cccccccc', 'send_time' => '00:00:00'],
        ]);

        $results = new NotificationRepository()->getNotificationsForUser(new AuthenticatedUser(['sub' => 'user_111']));

        self::assertCount(2, $results);
        self::assertInstanceOf(Notification::class, $results->first());
        self::assertEquals(['1', '2'], $results->pluck('id')->all());
    }

    public function testUpdateNotification(): void
    {
        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '+61400000000', 'lead_days' => 0, 'send_time' => '11:00:00'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'target' => '+61400000001', 'lead_days' => 0, 'send_time' => '11:00:00'],
        ]);

        $result = new NotificationRepository()->updateNotification(
            new Notification()->newQuery()->findOrFail('1'),
            new UpdateNotification(['lead_days' => 2, 'send_time' => '18:30:00']),
        );

        self::assertSame(2, $result->leadDays);
        self::assertSame('18:30:00', $result->sendTime);
        self::assertSame('+61400000000', $result->target);
        self::assertSame('18:30:00', DB::table('notifications')->where('id', '1')->value('send_time'));
        self::assertSame('11:00:00', DB::table('notifications')->where('id', '2')->value('send_time'));
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
            $table->softDeletes();
        });

        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('house_id');
            $table->string('name')->nullable();
            $table->string('type');
            $table->string('target');
            $table->integer('lead_days')->default(0);
            $table->integer('offset_days')->default(0);
            $table->string('send_time');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });

        DB::schema()->create('notification_logs', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('notification_id');
            $table->dateTime('scheduled_for');
            $table->string('status');
        });
    }
}
