<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Repositories\NotificationLogRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class NotificationLogRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testClaimCreatesAProcessingLog(): void
    {
        $log = new NotificationLogRepository()->claim($this->notification(), $this->slot());

        self::assertInstanceOf(NotificationLog::class, $log);
        self::assertSame(DeliveryStatus::PROCESSING, $log->status);
        self::assertSame('n1', DB::table('notification_logs')->value('notification_id'));
        self::assertSame('1985-10-26 01:30:00', DB::table('notification_logs')->value('scheduled_for'));
        self::assertSame('processing', DB::table('notification_logs')->value('status'));
    }

    public function testClaimGivesUpASlotThatIsBeingProcessed(): void
    {
        $repository = new NotificationLogRepository();
        $repository->claim($this->notification(), $this->slot());

        self::assertNull($repository->claim($this->notification(), $this->slot()));
        self::assertSame(1, DB::table('notification_logs')->count());
        self::assertSame('processing', DB::table('notification_logs')->value('status'));
    }

    public function testClaimGivesUpASlotThatWasSent(): void
    {
        $repository = new NotificationLogRepository();
        $log = $repository->claim($this->notification(), $this->slot());
        $repository->markSent($log, $this->slot());

        self::assertNull($repository->claim($this->notification(), $this->slot()));
        self::assertSame('sent', DB::table('notification_logs')->value('status'));
    }

    public function testClaimTakesAFailedSlotAgain(): void
    {
        $repository = new NotificationLogRepository();
        $first = $repository->claim($this->notification(), $this->slot());
        $repository->markFailed($first, 'Twilio\\Exceptions\\RestException (code 21211)');

        $second = $repository->claim($this->notification(), $this->slot());

        self::assertInstanceOf(NotificationLog::class, $second);
        self::assertSame($first->getKey(), $second->getKey());
        self::assertSame(DeliveryStatus::PROCESSING, $second->status);
        self::assertNull(DB::table('notification_logs')->value('failure_reason'));
        self::assertSame(1, DB::table('notification_logs')->count());
    }

    public function testClaimTakesAFailedSlotAgainOnlyOnce(): void
    {
        $repository = new NotificationLogRepository();
        $repository->markFailed($repository->claim($this->notification(), $this->slot()), 'failed');

        self::assertInstanceOf(NotificationLog::class, $repository->claim($this->notification(), $this->slot()));
        self::assertNull($repository->claim($this->notification(), $this->slot()));
    }

    public function testClaimTreatsOtherSlotsAndOtherNotificationsSeparately(): void
    {
        $repository = new NotificationLogRepository();
        $repository->claim($this->notification(), $this->slot());

        self::assertInstanceOf(NotificationLog::class, $repository->claim($this->notification(), $this->slot()->modify('+30 minutes')));
        self::assertInstanceOf(NotificationLog::class, $repository->claim($this->notification('n2'), $this->slot()));
        self::assertSame(3, DB::table('notification_logs')->count());
    }

    public function testMarkFailedRecordsTheReason(): void
    {
        $repository = new NotificationLogRepository();

        $repository->markFailed($repository->claim($this->notification(), $this->slot()), 'Twilio\\Exceptions\\RestException (code 21211)');

        self::assertSame('failed', DB::table('notification_logs')->value('status'));
        self::assertSame('Twilio\\Exceptions\\RestException (code 21211)', DB::table('notification_logs')->value('failure_reason'));
    }

    public function testMarkSentRecordsWhenItWasSent(): void
    {
        $repository = new NotificationLogRepository();

        $repository->markSent($repository->claim($this->notification(), $this->slot()), new DateTimeImmutable('1985-10-26 01:31:07', new DateTimeZone('UTC')));

        self::assertSame('sent', DB::table('notification_logs')->value('status'));
        self::assertSame('1985-10-26 01:31:07', DB::table('notification_logs')->value('sent_at'));
    }

    public function testStatusForFailsForASlotThatWasNeverClaimed(): void
    {
        $this->expectException(ModelNotFoundException::class);

        new NotificationLogRepository()->statusFor($this->notification(), $this->slot());
    }

    public function testStatusForReadsTheSlotsStatus(): void
    {
        $repository = new NotificationLogRepository();
        $repository->claim($this->notification(), $this->slot());

        self::assertSame(DeliveryStatus::PROCESSING, $repository->statusFor($this->notification(), $this->slot()));
    }

    private function migrate(): void
    {
        DB::schema()->create('notification_logs', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('notification_id');
            $table->dateTime('scheduled_for');
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'scheduled_for']);
        });
    }

    private function notification(string $id = 'n1'): Notification
    {
        return new Notification()->setAttribute('id', $id);
    }

    private function slot(): DateTimeImmutable
    {
        return new DateTimeImmutable('1985-10-26 01:30', new DateTimeZone('UTC'));
    }
}
