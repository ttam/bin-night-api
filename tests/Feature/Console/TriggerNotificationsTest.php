<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Clock\KnownClock;
use App\Services\Notifications\Exceptions\DeliveryFailedException;
use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use Tests\Feature\Api\ApiTestCase;

/**
 * These run the real command against the real tables, with only the SMS transport replaced. 1985-10-26 was a Saturday, so
 * the notification (one day ahead, at 18:30 in a UTC house) finds the Sunday bin.
 */
final class TriggerNotificationsTest extends ApiTestCase
{
    private const string SLOT = '1985-10-26 18:30';

    private RecordingTwilioService $twilio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLimits();

        $this->twilio = new RecordingTwilioService();
        $this->app->instance(TwilioServiceInterface::class, $this->twilio);
    }

    public function testADryRunClaimsNothingAndSendsNothing(): void
    {
        $this->givenANotificationIsDue();

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT, '--dry' => true])
            ->expectsOutputToContain('Would send to "+61400000000" with 1 bins (Rubbish)')
            ->expectsOutputToContain('1 due, 0 skipped, 1 would be sent')
            ->assertExitCode(0);

        self::assertSame([], $this->twilio->messages);
        self::assertSame(0, DB::table('notification_logs')->count());
    }

    public function testAFailedSlotIsRetriedByALaterScheduledRun(): void
    {
        $this->givenANotificationIsDue();
        $this->twilio->failure = new DeliveryFailedException('Twilio\\Exceptions\\RestException (code 20003)');
        $this->givenTheTimeIs('1985-10-26 18:30:10');

        $this->artisan('bin-night:trigger-notifications')->assertExitCode(1);

        self::assertSame('failed', DB::table('notification_logs')->value('status'));

        $this->twilio->failure = null;
        $this->givenTheTimeIs('1985-10-26 19:00:10');

        $this->artisan('bin-night:trigger-notifications')
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent, 0 failed')
            ->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
        self::assertSame('sent', DB::table('notification_logs')->value('status'));
        self::assertSame(1, DB::table('notification_logs')->count());
    }

    public function testANotificationWaitingForItsNumberToBeVerifiedIsSkippedNotFailed(): void
    {
        $notificationId = $this->givenANotificationIsDue();
        DB::table('phone_numbers')->update(['status' => 'pending', 'verified_at' => null]);

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])
            ->expectsOutputToContain('1 due, 1 skipped, 0 sent, 0 failed, 0 already sent, 0 in progress')
            ->assertExitCode(0);

        self::assertSame([], $this->twilio->messages);
        self::assertSame(0, DB::table('notification_logs')->where('notification_id', $notificationId)->count());

        // Once the number is verified the same slot is sent, if it is still inside the catch-up window.
        DB::table('phone_numbers')->update(['status' => 'verified', 'verified_at' => '1985-10-26 01:21:00']);

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
        self::assertSame('+61400000000', $this->twilio->messages[0]['to']);
    }

    public function testAFailedSlotIsRetriedWhenTheSlotIsRunAgain(): void
    {
        $notificationId = $this->givenANotificationIsDue();
        $this->twilio->failure = new DeliveryFailedException('Twilio\\Exceptions\\RestException (code 21211)');

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])
            ->expectsOutputToContain('1 due, 0 skipped, 0 sent, 1 failed, 0 already sent, 0 in progress')
            ->assertExitCode(1);

        $log = DB::table('notification_logs')->where('notification_id', $notificationId)->first();
        self::assertSame('failed', $log->status);
        self::assertSame('Twilio\\Exceptions\\RestException (code 21211)', $log->failure_reason);
        self::assertStringNotContainsString('+61400000000', (string) $log->failure_reason);
        self::assertSame([], $this->twilio->messages);

        $this->twilio->failure = null;

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent, 0 failed, 0 already sent, 0 in progress')
            ->assertExitCode(0);

        self::assertSame([['message' => 'Rubbish', 'to' => '+61400000000']], $this->twilio->messages);
        $log = DB::table('notification_logs')->where('notification_id', $notificationId)->first();
        self::assertSame('sent', $log->status);
        self::assertNull($log->failure_reason);
        self::assertNotNull($log->sent_at);
        self::assertSame(1, DB::table('notification_logs')->count());
    }

    public function testALateRunIsRoundedDownToTheQuarterHourSoItDoesNotSendTwice(): void
    {
        $this->givenANotificationIsDue();

        $this->artisan('bin-night:trigger-notifications', ['now' => '1985-10-26 18:30'])->assertExitCode(0);
        $this->artisan('bin-night:trigger-notifications', ['now' => '1985-10-26 18:47'])->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
    }

    public function testALateRunSendsTheSlotItMissedOnceOnly(): void
    {
        $this->givenANotificationIsDue();
        $this->givenTheTimeIs('1985-10-26 19:00:10');

        $this->artisan('bin-night:trigger-notifications')
            ->expectsOutputToContain('1985-10-26 17:00 to 1985-10-26 19:00: 1 due, 0 skipped, 1 sent, 0 failed, 0 already sent, 0 in progress')
            ->assertExitCode(0);

        self::assertSame([['message' => 'Rubbish', 'to' => '+61400000000']], $this->twilio->messages);
        self::assertSame('1985-10-26 18:30:00', DB::table('notification_logs')->value('scheduled_for'));

        $this->givenTheTimeIs('1985-10-26 19:30:10');

        $this->artisan('bin-night:trigger-notifications')
            ->expectsOutputToContain('1985-10-26 17:30 to 1985-10-26 19:30: 0 due, 0 skipped, 0 sent')
            ->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
    }

    public function testAnExplicitTimeDoesNotCatchUp(): void
    {
        $this->givenANotificationIsDue();

        $this->artisan('bin-night:trigger-notifications', ['now' => '1985-10-26 19:00'])
            ->expectsOutputToContain('1985-10-26 19:00: 0 due')
            ->assertExitCode(0);

        self::assertSame([], $this->twilio->messages);
    }

    public function testANotificationChangedAfterTheMissedSlotIsNotSentLate(): void
    {
        $notificationId = $this->givenANotificationIsDue();
        DB::table('notifications')->where('id', $notificationId)->update(['updated_at' => '1985-10-26 18:45:00']);
        $this->givenTheTimeIs('1985-10-26 19:00:10');

        $this->artisan('bin-night:trigger-notifications')
            ->expectsOutputToContain('1 due, 1 skipped, 0 sent')
            ->assertExitCode(0);

        self::assertSame([], $this->twilio->messages);
        self::assertSame(0, DB::table('notification_logs')->count());
    }

    public function testASlotOutsideTheWindowIsNotCaughtUp(): void
    {
        $this->givenANotificationIsDue();
        $this->givenTheTimeIs('1985-10-26 19:00:10');
        \config(['notifications.catch_up_hours' => 0]);

        $this->artisan('bin-night:trigger-notifications')
            ->expectsOutputToContain('1985-10-26 19:00: 0 due')
            ->assertExitCode(0);

        self::assertSame([], $this->twilio->messages);
    }

    public function testASlotThatIsBeingSentIsNotSentAgain(): void
    {
        $notificationId = $this->givenANotificationIsDue();
        DB::table('notification_logs')->insert([
            'id' => (string) Str::uuid(),
            'notification_id' => $notificationId,
            'scheduled_for' => '1985-10-26 18:30:00',
            'status' => 'processing',
            'created_at' => '1985-10-26 18:30:00',
            'updated_at' => '1985-10-26 18:30:00',
        ]);

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])
            ->expectsOutputToContain('1 due, 0 skipped, 0 sent, 0 failed, 0 already sent, 1 in progress')
            ->assertExitCode(0);

        self::assertSame([], $this->twilio->messages);
        self::assertSame('processing', DB::table('notification_logs')->value('status'));
    }

    public function testATimeThatDoesNotExistIsSentOnceAsIfTheClocksHadNotChanged(): void
    {
        // Melbourne puts its clocks forward at 02:00 on Sunday 4 October 2026, so 02:30 never happens that night.
        $this->givenANotificationIsDueAt('Australia/Melbourne', 'sunday', '02:30:00');

        foreach (['2026-10-03 15:30', '2026-10-03 16:00', '2026-10-03 16:15'] as $now) {
            $this->artisan('bin-night:trigger-notifications', ['now' => $now])->expectsOutputToContain('0 due')->assertExitCode(0);
        }

        // 03:30 in Melbourne that morning is 16:30 UTC.
        $this->artisan('bin-night:trigger-notifications', ['now' => '2026-10-03 16:30'])
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent')
            ->assertExitCode(0);
        $this->artisan('bin-night:trigger-notifications', ['now' => '2026-10-03 16:45'])->expectsOutputToContain('0 due')->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
        self::assertSame('2026-10-03 16:30:00', DB::table('notification_logs')->value('scheduled_for'));
    }

    public function testATimeThatHappensTwiceIsSentOnlyAtTheFirstShowing(): void
    {
        // Los Angeles puts its clocks back at 02:00 on Sunday 1 November 2026, so 01:30 is 08:30 UTC and again 09:30 UTC.
        $this->givenANotificationIsDueAt('America/Los_Angeles', 'sunday', '01:30:00');

        $this->artisan('bin-night:trigger-notifications', ['now' => '2026-11-01 08:30'])
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent')
            ->assertExitCode(0);
        $this->artisan('bin-night:trigger-notifications', ['now' => '2026-11-01 09:30'])
            ->expectsOutputToContain('0 due')
            ->assertExitCode(0);

        // A scheduled run an hour later, catching up the last two hours, does not send it again either.
        $this->givenTheTimeIs('2026-11-01 09:30:10');
        $this->artisan('bin-night:trigger-notifications')
            ->expectsOutputToContain('0 due')
            ->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
        self::assertSame('2026-11-01 08:30:00', DB::table('notification_logs')->value('scheduled_for'));
    }

    public function testAZoneAQuarterHourOffTheHourIsSentOnTime(): void
    {
        // 09:00 on Monday 1 June 2026 in Eucla (UTC+8:45) is 00:15 UTC, a time half-hour runs would never have reached.
        $this->givenANotificationIsDueAt('Australia/Eucla', 'monday', '09:00:00');

        $this->artisan('bin-night:trigger-notifications', ['now' => '2026-06-01 00:00'])
            ->expectsOutputToContain('0 due')
            ->assertExitCode(0);
        $this->artisan('bin-night:trigger-notifications', ['now' => '2026-06-01 00:15'])
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent')
            ->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
        self::assertSame('2026-06-01 00:15:00', DB::table('notification_logs')->value('scheduled_for'));
    }

    public function testBinsCreatedThroughTheApiAreInTheNotification(): void
    {
        $this->useLimits(binsPerHouse: 3);
        $houseId = $this->createHouse('Hill Valley', '1955-11-12 06:00:00');
        DB::table('houses')->where('id', $houseId)->update(['bin_night' => 'sunday', 'timezone' => 'UTC']);
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        $this->givenTheTimeIs('1985-10-26 12:00');

        // All three start on Sunday the 27th, the house's bin night.
        foreach (['weekly' => 'Rubbish', 'fortnightly' => 'Recycling', 'monthly' => 'Garden'] as $cycle => $name) {
            $this->postJson('/api/v1/bins', [
                'bin_color' => '#000000',
                'cycle' => $cycle,
                'house_id' => $houseId,
                'lid_color' => '#ffffff',
                'name' => $name,
                'start_date' => '1985-10-27',
            ])->assertCreated()->assertJsonPath('data.start_date', '1985-10-27');
        }

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent')
            ->assertExitCode(0);

        self::assertCount(1, $this->twilio->messages);
        $bins = \explode(', ', $this->twilio->messages[0]['message']);
        \sort($bins);
        self::assertSame(['Garden', 'Recycling', 'Rubbish'], $bins);

        // Nothing goes out before the start date: the slot before looks ahead to Sunday the 20th, a bin night a week earlier.
        $this->artisan('bin-night:trigger-notifications', ['now' => '1985-10-19 18:30'])
            ->expectsOutputToContain('1 due, 1 skipped, 0 sent')
            ->assertExitCode(0);
        self::assertCount(1, $this->twilio->messages);

        // A fortnight later only the weekly and fortnightly bins are due; the monthly one is a month on.
        $this->artisan('bin-night:trigger-notifications', ['now' => '1985-11-09 18:30'])->assertExitCode(0);

        $bins = \explode(', ', $this->twilio->messages[1]['message']);
        \sort($bins);
        self::assertSame(['Recycling', 'Rubbish'], $bins);
    }

    public function testNextWeeksSlotIsSentAgain(): void
    {
        $this->givenANotificationIsDue();

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])->assertExitCode(0);
        $this->artisan('bin-night:trigger-notifications', ['now' => '1985-11-02 18:30'])
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent')
            ->assertExitCode(0);

        self::assertCount(2, $this->twilio->messages);
        self::assertSame(2, DB::table('notification_logs')->count());
    }

    public function testRunningTheSameSlotTwiceSendsOnce(): void
    {
        $this->givenANotificationIsDue();

        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])
            ->expectsOutputToContain('1 due, 0 skipped, 1 sent, 0 failed, 0 already sent, 0 in progress')
            ->assertExitCode(0);
        $this->artisan('bin-night:trigger-notifications', ['now' => self::SLOT])
            ->expectsOutputToContain('0 due, 0 skipped, 0 sent, 0 failed, 0 already sent, 0 in progress')
            ->assertExitCode(0);

        self::assertSame([['message' => 'Rubbish', 'to' => '+61400000000']], $this->twilio->messages);
        self::assertSame(1, DB::table('notification_logs')->count());
    }

    /** Returns the id of a notification that is due in the slot, for a house whose bin night is Sunday with a weekly bin called "Rubbish". */
    private function givenANotificationIsDue(): string
    {
        $houseId = $this->createHouse('Hill Valley', '1955-11-12 06:00:00');
        DB::table('houses')->where('id', $houseId)->update(['bin_night' => 'sunday', 'timezone' => 'UTC']);

        $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00');

        return $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
    }

    /** A notification that is sent on the day itself (no lead days) at the given local time, for a weekly bin called "Rubbish". */
    private function givenANotificationIsDueAt(string $timezone, string $binNight, string $sendTime): void
    {
        $houseId = $this->createHouse('Hill Valley', '1955-11-12 06:00:00');
        DB::table('houses')->where('id', $houseId)->update(['bin_night' => $binNight, 'timezone' => $timezone]);

        $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        DB::table('notifications')->where('id', $notificationId)->update(['lead_days' => 0, 'send_time' => $sendTime]);
    }

    private function givenTheTimeIs(string $utc): void
    {
        $this->app->instance(ClockInterface::class, new KnownClock(new DateTimeImmutable($utc, new DateTimeZone('UTC'))));
    }
}
