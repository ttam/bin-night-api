<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\TriggerNotifications;
use App\DTO\Notification\DueNotification;
use App\Enums\DeliveryResult;
use App\Models\Bin;
use App\Models\Notification;
use App\Models\PhoneNumber;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Clock\KnownClock;
use App\Services\Notifications\Interfaces\NotificationDeliveryInterface;
use App\Services\Plan\ActiveEntities;
use App\Services\Plan\PlanLimits;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TriggerNotificationsTest extends TestCase
{
    private array $errors = [];

    private array $info = [];

    private array $warnings = [];

    /** @noinspection PhpUnhandledExceptionInspection */
    public static function getCatchUpWindows(): iterable
    {
        yield 'No catch-up' => [0, '1985-10-26 01:00', '1985-10-26 01:15'];

        yield 'One hour' => [1, '1985-10-26 00:00', '1985-10-26 00:15 to 1985-10-26 01:15'];

        yield 'Twelve hours' => [12, '1985-10-25 13:00', '1985-10-25 13:15 to 1985-10-26 01:15'];
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public static function getClockData(): iterable
    {
        yield 'Using "now" parameter' => [
            new KnownClock(new DateTimeImmutable('1970-01-01 00:00')),
            '1985-10-26 01:21',
        ];

        yield 'Using clock' => [
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            null,
        ];

        yield 'Using a clock in another timezone' => [
            new KnownClock(new DateTimeImmutable('1985-10-26 12:21', new DateTimeZone('+11:00'))),
            null,
        ];
    }

    public static function getInvalidNows(): iterable
    {
        yield 'Not a date' => ['example'];

        yield 'Empty' => [''];

        yield 'Wrong format' => ['1985-10-26T01:21'];

        yield 'Impossible day' => ['2026-02-31 02:30'];

        yield 'Impossible hour' => ['1985-10-26 25:00'];

        yield 'Impossible minute' => ['1985-10-26 01:99'];
    }

    public function testACaughtUpNotificationIsDeliveredForTheMomentItWasDue(): void
    {
        $dueAt = new DateTimeImmutable('1985-10-26 00:30', new DateTimeZone('UTC'));
        $notification = self::notification(1, 'user-1');
        $bins = new Collection([new Bin()->setAttribute('id', 1)]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($notification, $dueAt));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createMock(BinRepositoryInterface::class);
        $binRepository->expects($this->once())->method('getBinsForNotification')->with($notification, $dueAt)->willReturn($bins);

        /** @var \App\Services\Notifications\Interfaces\NotificationDeliveryInterface&\PHPUnit\Framework\MockObject\MockObject $delivery */
        $delivery = $this->createMock(NotificationDeliveryInterface::class);
        $delivery->expects($this->once())->method('deliver')->with($notification, $bins, $dueAt)->willReturn(DeliveryResult::SENT);

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(1),
            $delivery,
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertSame(['1985-10-26 00:15 to 1985-10-26 01:15: 1 due, 0 skipped, 1 sent, 0 failed, 0 already sent, 0 in progress.'], $this->info);
    }

    public function testActiveEntitiesAreLoadedOncePerUser(): void
    {
        $notifications = new Collection([
            self::notification(1, 'user-1'),
            self::notification(2, 'user-1'),
            self::notification(3, 'user-2'),
        ]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue(...$notifications->all()));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection());

        $activeEntityRepository = $this->createMock(ActiveEntityRepositoryInterface::class);
        $activeEntityRepository->expects($this->exactly(2))->method('getActiveEntitiesForUser')
            ->willReturnCallback(fn (): ActiveEntities => $this->makeActiveEntities([1, 2, 3], [], []));

        $this->makeCommand(null, [])->handle(
            $activeEntityRepository,
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $this->createStub(NotificationDeliveryInterface::class),
            $notificationRepository,
        );
    }

    public function testANegativeWindowIsTreatedAsNone(): void
    {
        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $notificationRepository->expects($this->once())->method('getDueNotifications')
            ->with(new DateTimeImmutable('1985-10-26 01:00', new DateTimeZone('UTC')), new DateTimeImmutable('1985-10-26 01:15', new DateTimeZone('UTC')))
            ->willReturn(new Collection());

        $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(),
            $this->createStub(BinRepositoryInterface::class),
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(-3),
            $this->createStub(NotificationDeliveryInterface::class),
            $notificationRepository,
        );
    }

    public function testAnExplicitTimeOnlyRunsThatMoment(): void
    {
        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $notificationRepository->expects($this->once())->method('getDueNotifications')
            ->with(new DateTimeImmutable('1985-10-26 01:00', new DateTimeZone('UTC')), new DateTimeImmutable('1985-10-26 01:15', new DateTimeZone('UTC')))
            ->willReturn(new Collection());

        $this->makeCommand('1985-10-26 01:21', [])->handle(
            $this->makeActiveEntityRepository(),
            $this->createStub(BinRepositoryInterface::class),
            $this->makeExceptionStub(ClockInterface::class, 'now'),
            $this->makeConfig(12),
            $this->createStub(NotificationDeliveryInterface::class),
            $notificationRepository,
        );

        self::assertSame(['1985-10-26 01:15: 0 due, 0 skipped, 0 sent, 0 failed, 0 already sent, 0 in progress.'], $this->info);
    }

    #[DataProvider('getCatchUpWindows')]
    public function testAScheduledRunCoversTheLastQuarterHourAndAsFarBackAsTheCatchUpAllows(int $catchUpHours, string $after, string $label): void
    {
        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $notificationRepository->expects($this->once())->method('getDueNotifications')
            ->with(new DateTimeImmutable($after, new DateTimeZone('UTC')), new DateTimeImmutable('1985-10-26 01:15', new DateTimeZone('UTC')))
            ->willReturn(new Collection());

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(),
            $this->createStub(BinRepositoryInterface::class),
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig($catchUpHours),
            $this->createStub(NotificationDeliveryInterface::class),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertSame([$label . ': 0 due, 0 skipped, 0 sent, 0 failed, 0 already sent, 0 in progress.'], $this->info);
    }

    public function testDryRunSendsOutput(): void
    {
        $notification = self::notification(1, 'user-1');
        $binCollection = new Collection([
            new Bin()->setAttribute('id', 1)->setAttribute('name', 'Example'),
            new Bin()->setAttribute('id', 2)->setAttribute('name', 'Other'),
        ]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($notification));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn($binCollection);

        $exitCode = $this->makeCommand(null, ['dry' => true])->handle(
            $this->makeActiveEntityRepository(),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $this->createStub(NotificationDeliveryInterface::class),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertCount(0, $this->errors);
        self::assertCount(2, $this->info);
        self::assertSame('<fg=yellow>[DRY] Would send to "04xxxxxxxx" with 2 bins (Example, Other)</>', $this->info[0]);
        self::assertSame('[DRY] 1985-10-26 01:15: 1 due, 0 skipped, 1 would be sent.', $this->info[1]);
    }

    public function testFailedDeliveryFailsTheRun(): void
    {
        $exitCode = $this->runWithDeliveries(DeliveryResult::SENT, DeliveryResult::FAILED);

        self::assertSame(1, $exitCode);
        self::assertSame(['1985-10-26 01:15: 2 due, 0 skipped, 1 sent, 1 failed, 0 already sent, 0 in progress.'], $this->info);
        self::assertSame(['Some notifications failed. Run this command again for the same slot to retry them.'], $this->errors);
        self::assertSame([], $this->warnings);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    #[DataProvider('getClockData')]
    public function testGetsTimeAndRoundsTimeAndDelivers(ClockInterface $clock, ?string $now): void
    {
        $after = new DateTimeImmutable('1985-10-26 01:00:00', new DateTimeZone('UTC'));
        $dueAt = new DateTimeImmutable('1985-10-26 01:15:00', new DateTimeZone('UTC'));
        $notification = self::notification(1, 'user-1');
        $binCollection = new Collection([
            new Bin()->setAttribute('id', 1),
            new Bin()->setAttribute('id', 2),
        ]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $notificationRepository->expects($this->once())->method('getDueNotifications')
            ->with($after, $dueAt)
            ->willReturn($this->makeDue($notification, $dueAt));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createMock(BinRepositoryInterface::class);
        $binRepository->expects($this->once())->method('getBinsForNotification')
            ->with($notification, $dueAt)
            ->willReturn($binCollection);

        /** @var \App\Services\Notifications\Interfaces\NotificationDeliveryInterface&\PHPUnit\Framework\MockObject\MockObject $delivery */
        $delivery = $this->createMock(NotificationDeliveryInterface::class);
        $delivery->expects($this->once())->method('deliver')
            ->with($notification, $binCollection, $dueAt)
            ->willReturn(DeliveryResult::SENT);

        $exitCode = $this->makeCommand($now, [])->handle(
            $this->makeActiveEntityRepository(),
            $binRepository,
            $clock,
            $this->makeConfig(),
            $delivery,
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertCount(0, $this->errors);
        self::assertSame(['1985-10-26 01:15: 1 due, 0 skipped, 1 sent, 0 failed, 0 already sent, 0 in progress.'], $this->info);
    }

    public function testInactiveBinsAreLeftOutOfTheNotification(): void
    {
        $notification = self::notification(1, 'user-1');
        $active = new Bin()->setAttribute('id', 1)->setAttribute('name', 'Active');
        $inactive = new Bin()->setAttribute('id', 2)->setAttribute('name', 'Inactive');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($notification));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection([$active, $inactive]));

        /** @var \App\Services\Notifications\Interfaces\NotificationDeliveryInterface&\PHPUnit\Framework\MockObject\MockObject $delivery */
        $delivery = $this->createMock(NotificationDeliveryInterface::class);
        $delivery->expects($this->once())->method('deliver')
            ->with($notification, new Collection([$active]), $this->anything())
            ->willReturn(DeliveryResult::SENT);

        $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(binIds: [1]),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $delivery,
            $notificationRepository,
        );
    }

    public function testInactiveNotificationIsSkipped(): void
    {
        $notification = self::notification(1, 'user-1');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($notification));

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(notificationIds: []),
            $this->makeExceptionStub(BinRepositoryInterface::class, 'getBinsForNotification'),
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $this->makeExceptionStub(NotificationDeliveryInterface::class, 'deliver'),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
    }

    public function testNotificationWaitingForItsNumberToBeVerifiedIsSkippedWithoutLookingForBins(): void
    {
        $notification = self::notification(1, 'user-1', verified: false);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($notification));

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(),
            $this->makeExceptionStub(BinRepositoryInterface::class, 'getBinsForNotification'),
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $this->makeExceptionStub(NotificationDeliveryInterface::class, 'deliver'),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertSame([], $this->errors);
        self::assertSame(['1985-10-26 01:15: 1 due, 1 skipped, 0 sent, 0 failed, 0 already sent, 0 in progress.'], $this->info);
    }

    public function testInProgressDeliveryWarnsButDoesNotFailTheRun(): void
    {
        $exitCode = $this->runWithDeliveries(DeliveryResult::IN_PROGRESS);

        self::assertSame(0, $exitCode);
        self::assertSame(['1985-10-26 01:15: 1 due, 0 skipped, 0 sent, 0 failed, 0 already sent, 1 in progress.'], $this->info);
        self::assertCount(1, $this->warnings);
        self::assertStringContainsString('notification_logs', $this->warnings[0]);
        self::assertSame([], $this->errors);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    #[DataProvider('getInvalidNows')]
    public function testInvalidNowReturnsInputOutputError(string $now): void
    {
        $exitCode = $this->makeCommand($now, [])->handle(
            $this->makeExceptionStub(ActiveEntityRepositoryInterface::class, 'getActiveEntitiesForUser'),
            $this->makeExceptionStub(BinRepositoryInterface::class, 'getBinsForNotification'),
            $this->makeExceptionStub(ClockInterface::class, 'now'),
            $this->makeConfig(),
            $this->makeExceptionStub(NotificationDeliveryInterface::class, 'deliver'),
            $this->makeExceptionStub(NotificationRepositoryInterface::class, 'getDueNotifications'),
        );

        self::assertSame(5, $exitCode);
        self::assertSame([\sprintf('Invalid date format "%s"', $now)], $this->errors);
    }

    public function testNothingIsDeliveredWhenThereAreNoBins(): void
    {
        $notification = self::notification(1, 'user-1');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($notification));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection());

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1970-01-01 00:00')),
            $this->makeConfig(),
            $this->makeExceptionStub(NotificationDeliveryInterface::class, 'deliver'),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertSame(['1970-01-01 00:00: 1 due, 1 skipped, 0 sent, 0 failed, 0 already sent, 0 in progress.'], $this->info);
    }

    public function testNothingIsSentWhenEveryBinIsInactive(): void
    {
        $notification = self::notification(1, 'user-1');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($notification));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection([
            new Bin()->setAttribute('id', 2)->setAttribute('name', 'Inactive'),
        ]));

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(binIds: [1]),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $this->makeExceptionStub(NotificationDeliveryInterface::class, 'deliver'),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
    }

    public function testNotificationsChangedAfterTheirSlotAreSkipped(): void
    {
        $changedAfter = self::notification(1)->setRawAttributes(['id' => 1, 'user_external_id' => 'user-1', 'updated_at' => new DateTimeImmutable('1985-10-26 01:10:00', new DateTimeZone('UTC'))]);
        $changedBefore = self::notification(2)->setRawAttributes(['id' => 2, 'user_external_id' => 'user-1', 'updated_at' => new DateTimeImmutable('1985-10-26 00:59:59', new DateTimeZone('UTC'))]);
        $neverChanged = self::notification(3, 'user-1');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue($changedAfter, $changedBefore, $neverChanged));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection([new Bin()->setAttribute('id', 1)]));

        /** @var \App\Services\Notifications\Interfaces\NotificationDeliveryInterface&\PHPUnit\Framework\MockObject\MockObject $delivery */
        $delivery = $this->createMock(NotificationDeliveryInterface::class);
        $delivery->expects($this->exactly(2))->method('deliver')
            ->with($this->logicalOr($this->identicalTo($changedBefore), $this->identicalTo($neverChanged)), $this->anything(), $this->anything())
            ->willReturn(DeliveryResult::SENT);

        $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(notificationIds: [1, 2, 3]),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $delivery,
            $notificationRepository,
        );

        self::assertSame(['1985-10-26 01:15: 3 due, 1 skipped, 2 sent, 0 failed, 0 already sent, 0 in progress.'], $this->info);
    }

    public function testReportsEachOutcomeSeparately(): void
    {
        $exitCode = $this->runWithDeliveries(
            DeliveryResult::SENT,
            DeliveryResult::SENT,
            DeliveryResult::ALREADY_SENT,
            DeliveryResult::IN_PROGRESS,
        );

        self::assertSame(0, $exitCode);
        self::assertSame(['1985-10-26 01:15: 4 due, 0 skipped, 2 sent, 0 failed, 1 already sent, 1 in progress.'], $this->info);
    }

    /**
     * @param list<int> $notificationIds
     * @param list<int> $binIds
     * @param list<int> $houseIds
     */
    /** A due notification whose number is verified, unless it is said not to be. */
    private static function notification(int $id, string $user = 'user-1', bool $verified = true): Notification
    {
        return new Notification()
            ->setAttribute('id', $id)
            ->setAttribute('user_external_id', $user)
            ->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '04xxxxxxxx')->setAttribute('status', $verified ? 'verified' : 'pending'));
    }

    private function makeActiveEntities(array $notificationIds, array $binIds, array $houseIds): ActiveEntities
    {
        return new ActiveEntities(
            new PlanLimits(1, 2, 2, []),
            $houseIds,
            $binIds,
            $notificationIds,
        );
    }

    /**
     * By default notification 1 and bins 1 and 2, which the tests above use, are all active.
     *
     * @param list<int> $notificationIds
     * @param list<int> $binIds
     */
    private function makeActiveEntityRepository(array $notificationIds = [1], array $binIds = [1, 2]): ActiveEntityRepositoryInterface
    {
        $repository = $this->createStub(ActiveEntityRepositoryInterface::class);
        $repository->method('getActiveEntitiesForUser')->willReturn($this->makeActiveEntities($notificationIds, $binIds, []));

        return $repository;
    }

    private function makeCommand(?string $now, array $options): TriggerNotifications
    {
        $pushError = Closure::bind(
            fn (string $m): string => $this->errors[] = $m,
            $this,
            self::class
        );
        $pushInfo = Closure::bind(
            fn (string $m): string => $this->info[] = $m,
            $this,
            self::class
        );

        $pushWarning = Closure::bind(
            fn (string $m): string => $this->warnings[] = $m,
            $this,
            self::class
        );

        return new class($now, $options, $pushError, $pushInfo, $pushWarning) extends TriggerNotifications {
            /** @noinspection PhpMissingParentConstructorInspection */
            public function __construct(
                private readonly ?string $now,
                private readonly array $options,
                private readonly Closure $pushError,
                private readonly Closure $pushInfo,
                private readonly Closure $pushWarning,
            ) {
            }

            public function argument($key = null): ?string
            {
                return $key === 'now' ? $this->now : null;
            }

            public function error($string, $verbosity = null): void
            {
                ($this->pushError)($string);
            }

            public function info($string, $verbosity = null): void
            {
                ($this->pushInfo)($string);
            }

            public function option($key = null): mixed
            {
                return $this->options[$key] ?? null;
            }

            public function warn($string, $verbosity = null): void
            {
                ($this->pushWarning)($string);
            }
        };
    }

    private function makeConfig(int $catchUpHours = 0): Repository
    {
        return new Repository(['notifications' => ['catch_up_hours' => $catchUpHours]]);
    }

    /** @return \Illuminate\Support\Collection<int, DueNotification> */
    private function makeDue(Notification $first, DateTimeImmutable|Notification ...$rest): Collection
    {
        $dueAt = new DateTimeImmutable('1985-10-26 01:00', new DateTimeZone('UTC'));
        $notifications = [$first];

        foreach ($rest as $item) {
            if ($item instanceof DateTimeImmutable) {
                $dueAt = $item;

                continue;
            }

            $notifications[] = $item;
        }

        return new Collection(\array_map(static fn (Notification $notification): DueNotification => new DueNotification($notification, $dueAt), $notifications));
    }

    /**
     * @template T of object
     * @param class-string<T> $originalClassName
     * @param non-empty-string $method
     *
     * @return T&\PHPUnit\Framework\MockObject\MockObject
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    private function makeExceptionStub(string $originalClassName, string $method): Stub
    {
        /** @var T&\PHPUnit\Framework\MockObject\MockObject $stub */
        $stub = $this->createStub($originalClassName);
        $stub->method($method)->willThrowException(new \RuntimeException('Should not be called'));

        return $stub;
    }

    /** Runs the command with one due notification for each result, which the delivery service returns in order. */
    private function runWithDeliveries(DeliveryResult ...$results): int
    {
        $notifications = new Collection(\array_map(
            static fn (int $id): Notification => self::notification($id),
            \range(1, \count($results)),
        ));

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($this->makeDue(...$notifications->all()));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection([new Bin()->setAttribute('id', 1)]));

        /** @var \App\Services\Notifications\Interfaces\NotificationDeliveryInterface&\PHPUnit\Framework\MockObject\MockObject $delivery */
        $delivery = $this->createStub(NotificationDeliveryInterface::class);
        $delivery->method('deliver')->willReturn(...$results);

        return $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(notificationIds: \range(1, \count($results))),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeConfig(),
            $delivery,
            $notificationRepository,
        );
    }
}
