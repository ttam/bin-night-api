<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\TriggerNotifications;
use App\Events\NotificationEvent;
use App\Models\Bin;
use App\Models\Notification;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Clock\KnownClock;
use App\Services\Plan\ActiveEntities;
use App\Services\Plan\PlanLimits;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TriggerNotificationsTest extends TestCase
{
    private array $errors = [];

    private array $info = [];

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
    }

    public function testActiveEntitiesAreLoadedOncePerUser(): void
    {
        $notifications = new Collection([
            new Notification()->setAttribute('id', 1)->setAttribute('user_external_id', 'user-1'),
            new Notification()->setAttribute('id', 2)->setAttribute('user_external_id', 'user-1'),
            new Notification()->setAttribute('id', 3)->setAttribute('user_external_id', 'user-2'),
        ]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn($notifications);

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
            $this->createStub(Dispatcher::class),
            $notificationRepository,
        );
    }

    public function testDryRunSendsOutput(): void
    {
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('user_external_id', 'user-1')->setAttribute('target', '04xxxxxxxx');
        $binCollection = new Collection([
            new Bin()->setAttribute('id', 1)->setAttribute('name', 'Example'),
            new Bin()->setAttribute('id', 2)->setAttribute('name', 'Other'),
        ]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn(new Collection([$notification]));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn($binCollection);

        $exitCode = $this->makeCommand(null, ['dry' => true])->handle(
            $this->makeActiveEntityRepository(),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->createStub(Dispatcher::class),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertCount(0, $this->errors);
        self::assertCount(2, $this->info);
        self::assertSame('<fg=yellow>[DRY] Would dispatch NotificationEvent for notification "04xxxxxxxx" with 2 bins (Example, Other)</>', $this->info[0]);
        self::assertSame('Processed 1 notifications for 1985-10-26 01:00.', $this->info[1]);
    }

    public function testEventIsNotDispatchedWhenThereAreNoBins(): void
    {
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('user_external_id', 'user-1')->setAttribute('target', '04xxxxxxxx');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn(new Collection([$notification]));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection());

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1970-01-01 00:00')),
            $this->makeExceptionStub(Dispatcher::class, 'dispatch'),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    #[DataProvider('getClockData')]
    public function testGetsTimeAndRoundsTimeAndDispatches(ClockInterface $clock, ?string $now): void
    {
        $roundedDateTime = new DateTimeImmutable('1985-10-26 01:00:00', new DateTimeZone('UTC'));
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('user_external_id', 'user-1');
        $binCollection = new Collection([
            new Bin()->setAttribute('id', 1),
            new Bin()->setAttribute('id', 2),
        ]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $notificationRepository->expects($this->once())->method('getDueNotifications')
            ->with($roundedDateTime)
            ->willReturn(new Collection([$notification]));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createMock(BinRepositoryInterface::class);
        $binRepository->expects($this->once())->method('getBinsForNotification')
            ->with($notification, $roundedDateTime)
            ->willReturn($binCollection);

        /** @var \Illuminate\Contracts\Events\Dispatcher&\PHPUnit\Framework\MockObject\MockObject $dispatcher */
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->exactly(1))->method('dispatch')
            ->with(new NotificationEvent($notification, $binCollection));

        $exitCode = $this->makeCommand($now, [])->handle(
            $this->makeActiveEntityRepository(),
            $binRepository,
            $clock,
            $dispatcher,
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertCount(0, $this->errors);
    }

    public function testInactiveBinsAreLeftOutOfTheNotification(): void
    {
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('user_external_id', 'user-1');
        $active = new Bin()->setAttribute('id', 1)->setAttribute('name', 'Active');
        $inactive = new Bin()->setAttribute('id', 2)->setAttribute('name', 'Inactive');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn(new Collection([$notification]));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection([$active, $inactive]));

        /** @var \Illuminate\Contracts\Events\Dispatcher&\PHPUnit\Framework\MockObject\MockObject $dispatcher */
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with(new NotificationEvent($notification, new Collection([$active])));

        $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(binIds: [1]),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $dispatcher,
            $notificationRepository,
        );
    }

    public function testInactiveNotificationIsSkipped(): void
    {
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('user_external_id', 'user-1');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn(new Collection([$notification]));

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(notificationIds: []),
            $this->makeExceptionStub(BinRepositoryInterface::class, 'getBinsForNotification'),
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeExceptionStub(Dispatcher::class, 'dispatch'),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testInvalidNowReturnsInputOutputError(): void
    {
        $exitCode = $this->makeCommand('example', [])->handle(
            $this->makeExceptionStub(ActiveEntityRepositoryInterface::class, 'getActiveEntitiesForUser'),
            $this->makeExceptionStub(BinRepositoryInterface::class, 'getBinsForNotification'),
            $this->makeExceptionStub(ClockInterface::class, 'now'),
            $this->makeExceptionStub(Dispatcher::class, 'dispatch'),
            $this->makeExceptionStub(NotificationRepositoryInterface::class, 'getDueNotifications'),
        );

        self::assertSame(5, $exitCode);
        self::assertSame(['Invalid date format "example"'], $this->errors);
    }

    public function testNothingIsSentWhenEveryBinIsInactive(): void
    {
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('user_external_id', 'user-1');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn(new Collection([$notification]));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection([
            new Bin()->setAttribute('id', 2)->setAttribute('name', 'Inactive'),
        ]));

        $exitCode = $this->makeCommand(null, [])->handle(
            $this->makeActiveEntityRepository(binIds: [1]),
            $binRepository,
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->makeExceptionStub(Dispatcher::class, 'dispatch'),
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
    }

    /**
     * @param list<int> $notificationIds
     * @param list<int> $binIds
     * @param list<int> $houseIds
     */
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

        return new class($now, $options, $pushError, $pushInfo) extends TriggerNotifications {
            /** @noinspection PhpMissingParentConstructorInspection */
            public function __construct(
                private readonly ?string $now,
                private readonly array $options,
                private readonly Closure $pushError,
                private readonly Closure $pushInfo,
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
        };
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
}
