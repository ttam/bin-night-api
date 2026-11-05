<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\DueNotification;
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

        DB::table('phone_numbers')->insert([
            ['id' => 'p1', 'number' => '+61400000000', 'status' => 'verified'],
            ['id' => 'p2', 'number' => '+61400000001', 'status' => 'verified'],
            ['id' => 'p3', 'number' => '04aaaaaaaa', 'status' => 'verified'],
        ]);
    }

    public function testCreateNotification(): void
    {
        DB::table('users')->insert([['id' => '1', 'external_id' => 'user_111']]);

        $notification = new NotificationRepository()->createNotification(
            new AuthenticatedUser(['sub' => 'user_111']),
            new CreateNotification('house-1', 2, 'Marty\'s Phone', 'p1', '18:30:00', NotificationType::SMS),
        );

        self::assertSame(2, $notification->leadDays);
        self::assertSame('Marty\'s Phone', $notification->name);
        self::assertSame('p1', $notification->phoneNumberId);
        self::assertSame('18:30:00', $notification->sendTime);
        self::assertTrue($notification->relationLoaded('phoneNumber'));
        self::assertSame('+61400000000', $notification->target);
        self::assertSame(NotificationType::SMS, $notification->type);
        self::assertSame('1', $notification->userId);
        self::assertSame('house-1', $notification->getAttribute('house_id'));
        self::assertSame('18:30:00', DB::table('notifications')->value('send_time'));
    }

    public function testDeleteNotification(): void
    {
        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'name' => 'Marty\'s Phone', 'type' => 'sms', 'phone_number_id' => 'p1', 'send_time' => '11:00:00'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'name' => 'Keep', 'type' => 'sms', 'phone_number_id' => 'p2', 'send_time' => '11:00:00'],
        ]);

        new NotificationRepository()->deleteNotification(new Notification()->newQuery()->findOrFail('1'));

        self::assertSame(['2'], new Notification()->newQuery()->pluck('id')->all());
        self::assertNotNull(DB::table('notifications')->where('id', '1')->value('deleted_at'));
        self::assertSame('1-Marty\'s Phone', DB::table('notifications')->where('id', '1')->value('name'));
        self::assertSame('Keep', DB::table('notifications')->where('id', '2')->value('name'));
    }

    public function testGetDueNotificationsAreDueOncePerLocalDateAcrossAWindowOfDays(): void
    {
        $this->givenHouses(['1' => 'UTC']);
        DB::table('notifications')->insert([$this->row('1', '1', '09:00:00')]);

        self::assertSame(
            ['1 1985-10-24 09:00', '1 1985-10-25 09:00', '1 1985-10-26 09:00'],
            $this->due('1985-10-24 08:00', '1985-10-26 09:00'),
        );
    }

    public function testGetDueNotificationsComeWithTheirPhoneNumbers(): void
    {
        $this->givenHouses(['1' => 'UTC']);
        DB::table('notifications')->insert([$this->row('1', '1', '09:00:00')]);

        $due = new NotificationRepository()->getDueNotifications(
            new DateTimeImmutable('1985-10-26 08:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('1985-10-26 09:00', new DateTimeZone('UTC')),
        );

        self::assertCount(1, $due);
        self::assertTrue($due->first()->notification->relationLoaded('phoneNumber'));
        self::assertSame('04aaaaaaaa', $due->first()->notification->target);
    }

    public function testGetDueNotificationsEveryNotificationIsDueExactlyOnceTheNightClocksGoBack(): void
    {
        // Los Angeles turns its clocks back at 02:00 on Sunday 1 November 2026, so 01:00 to 01:59 happens twice.
        $this->givenHouses(['1' => 'America/Los_Angeles']);
        DB::table('notifications')->insert([
            $this->row('0030', '1', '00:30:00'),
            $this->row('0100', '1', '01:00:00'),
            $this->row('0130', '1', '01:30:00'),
            $this->row('0200', '1', '02:00:00'),
            $this->row('0230', '1', '02:30:00'),
        ]);

        // Every quarter hour from 06:45 to 11:00 UTC, which is the whole of that night, one run after another.
        $all = [];

        for ($slot = new DateTimeImmutable('2026-11-01 06:45', new DateTimeZone('UTC')); $slot <= new DateTimeImmutable('2026-11-01 11:00', new DateTimeZone('UTC')); $slot = $slot->modify('+15 minutes')) {
            $all = [...$all, ...$this->due($slot->modify('-15 minutes')->format('Y-m-d H:i'), $slot->format('Y-m-d H:i'))];
        }

        // The repeated hour is sent at its first showing, and the second showing (09:00 to 10:00 UTC) sends nothing.
        self::assertSame([
            '0030 2026-11-01 07:30',
            '0100 2026-11-01 08:00',
            '0130 2026-11-01 08:30',
            '0200 2026-11-01 10:00',
            '0230 2026-11-01 10:30',
        ], $all);
    }

    public function testGetDueNotificationsEveryNotificationIsDueExactlyOnceTheNightClocksGoForward(): void
    {
        // Melbourne puts its clocks forward at 02:00 on Sunday 4 October 2026, so 02:00 to 02:59 never happens.
        $this->givenHouses(['1' => 'Australia/Melbourne']);
        DB::table('notifications')->insert([
            $this->row('0100', '1', '01:00:00'),
            $this->row('0130', '1', '01:30:00'),
            $this->row('0200', '1', '02:00:00'),
            $this->row('0230', '1', '02:30:00'),
            $this->row('0300', '1', '03:00:00'),
            $this->row('0330', '1', '03:30:00'),
            $this->row('0400', '1', '04:00:00'),
        ]);

        $all = [];

        for ($slot = new DateTimeImmutable('2026-10-03 14:45', new DateTimeZone('UTC')); $slot <= new DateTimeImmutable('2026-10-03 17:30', new DateTimeZone('UTC')); $slot = $slot->modify('+15 minutes')) {
            $all = [...$all, ...$this->due($slot->modify('-15 minutes')->format('Y-m-d H:i'), $slot->format('Y-m-d H:i'))];
        }

        // A time that does not exist is sent as if the clocks had not changed: 02:00 at 03:00 and 02:30 at 03:30.
        self::assertSame([
            '0100 2026-10-03 15:00',
            '0130 2026-10-03 15:30',
            '0200 2026-10-03 16:00',
            '0300 2026-10-03 16:00',
            '0230 2026-10-03 16:30',
            '0330 2026-10-03 16:30',
            '0400 2026-10-03 17:00',
        ], $all);
    }

    public function testGetDueNotificationsFindsRecordsForMultipleTimeZones(): void
    {
        $this->givenHouses(['1' => 'Australia/Melbourne', '2' => 'America/Los_Angeles']);

        DB::table('notifications')->insert([
            // Melbourne, which is 10 hours ahead of UTC on this date
            $this->row('3', '1', '11:15:00'),
            $this->row('4', '1', '11:00:00'),

            // Los Angeles, which is 7 hours behind
            $this->row('5', '2', '18:15:00'),
            $this->row('6', '2', '19:15:00'),
        ]);

        $results = new NotificationRepository()->getDueNotifications(
            new DateTimeImmutable('1985-10-26 01:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('1985-10-26 01:15', new DateTimeZone('UTC')),
        );

        self::assertCount(2, $results);
        self::assertInstanceOf(DueNotification::class, $results->first());
        self::assertSame(['3', '5'], $results->map(static fn (DueNotification $due): string => (string) $due->notification->getKey())->all());
        self::assertSame('user_111', $results->first()->notification->userExternalId);
        self::assertSame('1985-10-26 01:15', $results->first()->dueAt->format('Y-m-d H:i'));
    }

    public function testGetDueNotificationsIsEmptyWhenThereAreNoNotifications(): void
    {
        self::assertSame([], $this->due('1985-10-26 01:00', '1985-10-26 01:15'));
    }

    public function testGetDueNotificationsLeavesOutDeletedNotifications(): void
    {
        $this->givenHouses(['1' => 'UTC']);
        DB::table('notifications')->insert([
            [...$this->row('kept', '1', '01:00:00'), 'deleted_at' => null],
            [...$this->row('deleted', '1', '01:00:00'), 'deleted_at' => '1985-10-20 00:00:00'],
        ]);

        self::assertSame(['kept 1985-10-26 01:00'], $this->due('1985-10-26 00:45', '1985-10-26 01:00'));
    }

    public function testGetDueNotificationsLeavesOutOnlyDeliveriesThatWereSent(): void
    {
        $this->givenHouses(['1' => 'UTC']);
        DB::table('notifications')->insert([
            $this->row('sent', '1', '01:00:00'),
            $this->row('failed', '1', '01:00:00'),
            $this->row('processing', '1', '01:00:00'),
            $this->row('sent-yesterday', '1', '01:00:00'),
            $this->row('never-tried', '1', '01:00:00'),
        ]);

        DB::table('notification_logs')->insert([
            ['id' => 'l1', 'notification_id' => 'sent', 'scheduled_for' => '1985-10-26 01:00:00', 'status' => 'sent'],
            ['id' => 'l2', 'notification_id' => 'failed', 'scheduled_for' => '1985-10-26 01:00:00', 'status' => 'failed'],
            ['id' => 'l3', 'notification_id' => 'processing', 'scheduled_for' => '1985-10-26 01:00:00', 'status' => 'processing'],
            ['id' => 'l4', 'notification_id' => 'sent-yesterday', 'scheduled_for' => '1985-10-25 01:00:00', 'status' => 'sent'],
        ]);

        $ids = $this->due('1985-10-25 23:00', '1985-10-26 01:00');
        \sort($ids);

        self::assertSame([
            'failed 1985-10-26 01:00',
            'never-tried 1985-10-26 01:00',
            'processing 1985-10-26 01:00',
            'sent-yesterday 1985-10-26 01:00',
        ], $ids);
    }

    public function testGetDueNotificationsTheWindowExcludesItsStartAndIncludesItsEnd(): void
    {
        $this->givenHouses(['1' => 'UTC']);
        DB::table('notifications')->insert([$this->row('1', '1', '09:00:00')]);

        self::assertSame(['1 1985-10-26 09:00'], $this->due('1985-10-26 08:45', '1985-10-26 09:00'));
        self::assertSame([], $this->due('1985-10-26 09:00', '1985-10-26 09:15'));
    }

    public function testGetDueNotificationsWorkForZonesThatAreAQuarterHourOffTheHour(): void
    {
        $this->givenHouses(['1' => 'Australia/Eucla', '2' => 'Asia/Kathmandu', '3' => 'Pacific/Chatham']);
        DB::table('notifications')->insert([
            $this->row('eucla', '1', '09:00:00'),
            $this->row('kathmandu', '2', '09:00:00'),
            $this->row('chatham', '3', '09:00:00'),
        ]);

        // 09:00 in each of them falls at :15 past the hour in UTC, a time a half-hour grid would never reach.
        self::assertSame([
            'chatham 2026-05-31 20:15',
            'eucla 2026-06-01 00:15',
            'kathmandu 2026-06-01 03:15',
        ], $this->due('2026-05-31 20:00', '2026-06-01 03:15'));
    }

    public function testGetNotificationsForUser(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'phone_number_id' => 'p1', 'send_time' => '00:00:00'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'phone_number_id' => 'p2', 'send_time' => '00:00:00'],
            ['id' => '3', 'user_id' => '2', 'house_id' => '1', 'type' => 'sms', 'phone_number_id' => 'p3', 'send_time' => '00:00:00'],
        ]);

        $results = new NotificationRepository()->getNotificationsForUser(new AuthenticatedUser(['sub' => 'user_111']));

        self::assertCount(2, $results);
        self::assertInstanceOf(Notification::class, $results->first());
        self::assertEquals(['1', '2'], $results->pluck('id')->all());
        self::assertSame(['+61400000000', '+61400000001'], $results->map(static fn (Notification $notification): string => $notification->target)->all());
        self::assertTrue($results->every(static fn (Notification $notification): bool => $notification->relationLoaded('phoneNumber')));
    }

    public function testUpdateNotification(): void
    {
        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'phone_number_id' => 'p1', 'lead_days' => 0, 'send_time' => '11:00:00'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'phone_number_id' => 'p2', 'lead_days' => 0, 'send_time' => '11:00:00'],
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

    public function testUpdateNotificationMovesItToAnotherPhoneNumber(): void
    {
        DB::table('notifications')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'type' => 'sms', 'phone_number_id' => 'p1', 'lead_days' => 0, 'send_time' => '11:00:00'],
        ]);

        $result = new NotificationRepository()->updateNotification(
            new Notification()->newQuery()->findOrFail('1'),
            new UpdateNotification(['phone_number_id' => 'p2']),
        );

        self::assertSame('p2', $result->phoneNumberId);
        self::assertSame('+61400000001', $result->target);
        self::assertSame('p2', DB::table('notifications')->where('id', '1')->value('phone_number_id'));
    }

    /**
     * @return list<string> "<id> <the instant it is due, in UTC>" for each notification due in the window, in order
     */
    private function due(string $after, string $until): array
    {
        return new NotificationRepository()
            ->getDueNotifications(new DateTimeImmutable($after, new DateTimeZone('UTC')), new DateTimeImmutable($until, new DateTimeZone('UTC')))
            ->map(static fn (DueNotification $due): string => $due->notification->getKey() . ' ' . $due->dueAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))
            ->all();
    }

    /** @param array<string, string> $timezones house id => timezone */
    private function givenHouses(array $timezones): void
    {
        DB::table('users')->insertOrIgnore(['id' => '1', 'external_id' => 'user_111']);

        foreach ($timezones as $id => $timezone) {
            DB::table('houses')->insert(['id' => (string) $id, 'timezone' => $timezone]);
        }
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
            $table->softDeletes();
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('timezone');
            $table->softDeletes();
        });

        DB::schema()->create('phone_numbers', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('number');
            $table->string('status');
            $table->softDeletes();
        });

        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('house_id');
            $table->string('name')->nullable();
            $table->string('type');
            $table->string('phone_number_id');
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

    /** @return array<string, string> */
    private function row(string $id, string $house, string $sendTime): array
    {
        return ['id' => $id, 'user_id' => '1', 'house_id' => $house, 'type' => 'sms', 'phone_number_id' => 'p3', 'send_time' => $sendTime];
    }
}
