<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\TriggerNotifications;
use App\Events\NotificationEvent;
use App\Models\Bin;
use App\Models\Notification;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Clock\KnownClock;
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

    public function testDryRunSendsOutput(): void
    {
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('target', '04xxxxxxxx');
        $binCollection = new Collection([
            new Bin()->setAttribute('name', 'Example'),
            new Bin()->setAttribute('name', 'Other'),
        ]);

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn(new Collection([$notification]));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn($binCollection);

        $exitCode = $this->makeCommand(null, ['dry' => true])->handle(
            new KnownClock(new DateTimeImmutable('1985-10-26 01:21', new DateTimeZone('UTC'))),
            $this->createStub(Dispatcher::class),
            $binRepository,
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
        $notification = new Notification()->setAttribute('id', 1)->setAttribute('target', '04xxxxxxxx');

        /** @var \App\Repositories\Interfaces\NotificationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $notificationRepository */
        $notificationRepository = $this->createStub(NotificationRepositoryInterface::class);
        $notificationRepository->method('getDueNotifications')->willReturn(new Collection([$notification]));

        /** @var \App\Repositories\Interfaces\BinRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $binRepository */
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection());

        $exitCode = $this->makeCommand(null, [])->handle(
            new KnownClock(new DateTimeImmutable('1970-01-01 00:00')),
            $this->makeExceptionStub(Dispatcher::class, 'dispatch'),
            $binRepository,
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    #[DataProvider('getClockData')]
    public function testGetsTimeAndRoundsTimeAndDispatches(ClockInterface $clock, ?string $now): void
    {
        $roundedDateTime = new DateTimeImmutable('1985-10-26 01:00:00', new DateTimeZone('UTC'));
        $notification = new Notification()->setAttribute('id', 1);
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
            $clock,
            $dispatcher,
            $binRepository,
            $notificationRepository,
        );

        self::assertSame(0, $exitCode);
        self::assertCount(0, $this->errors);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testInvalidNowReturnsInputOutputError(): void
    {
        $exitCode = $this->makeCommand('example', [])->handle(
            $this->makeExceptionStub(ClockInterface::class, 'now'),
            $this->makeExceptionStub(Dispatcher::class, 'dispatch'),
            $this->makeExceptionStub(BinRepositoryInterface::class, 'getBinsForNotification'),
            $this->makeExceptionStub(NotificationRepositoryInterface::class, 'getDueNotifications'),
        );

        self::assertSame(5, $exitCode);
        self::assertSame(['Invalid date format "example"'], $this->errors);
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
