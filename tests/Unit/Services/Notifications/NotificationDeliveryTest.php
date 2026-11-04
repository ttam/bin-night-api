<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications;

use App\Enums\DeliveryResult;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Factories\Interfaces\NotificationSenderFactoryInterface;
use App\Models\Bin;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\PhoneNumber;
use App\Repositories\Interfaces\NotificationLogRepositoryInterface;
use App\Services\Clock\KnownClock;
use App\Services\Notifications\Exceptions\DeliveryFailedException;
use App\Services\Notifications\Interfaces\NotificationSenderInterface;
use App\Services\Notifications\NotificationDelivery;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationDeliveryTest extends TestCase
{
    public static function getUnclaimedStatuses(): iterable
    {
        yield 'Another run is sending it' => [DeliveryStatus::PROCESSING, DeliveryResult::IN_PROGRESS];

        yield 'It failed and another run took it first' => [DeliveryStatus::FAILED, DeliveryResult::IN_PROGRESS];

        yield 'It was sent' => [DeliveryStatus::SENT, DeliveryResult::ALREADY_SENT];
    }

    public function testDeliverClaimsThenSendsThenMarksSent(): void
    {
        $log = new NotificationLog();
        $bins = new Collection([new Bin()]);

        $logs = $this->createMock(NotificationLogRepositoryInterface::class);
        $logs->expects($this->once())->method('claim')->with($this->notification(), $this->slot())->willReturn($log);
        $logs->expects($this->once())->method('markSent')->with($log, $this->now());
        $logs->expects($this->never())->method('markFailed');

        $sender = $this->createMock(NotificationSenderInterface::class);
        $sender->expects($this->once())->method('send')->with('+61400000000', $bins);

        $result = new NotificationDelivery($this->clock(), $this->factory($sender), $this->createStub(LoggerInterface::class), $logs)
            ->deliver($this->notification(), $bins, $this->slot());

        self::assertSame(DeliveryResult::SENT, $result);
    }

    public function testDeliverMarksAFailureAndLogsItWithoutThePhoneNumber(): void
    {
        $log = new NotificationLog();

        $logs = $this->createMock(NotificationLogRepositoryInterface::class);
        $logs->method('claim')->willReturn($log);
        $logs->expects($this->once())->method('markFailed')->with($log, 'Twilio\\Exceptions\\RestException (code 21211)');
        $logs->expects($this->never())->method('markSent');

        $sender = $this->createStub(NotificationSenderInterface::class);
        $sender->method('send')->willThrowException(new DeliveryFailedException('Twilio\\Exceptions\\RestException (code 21211)'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Notification delivery failed.', [
            'notification' => 'n1',
            'reason' => 'Twilio\\Exceptions\\RestException (code 21211)',
            'scheduled_for' => '1985-10-26 01:30',
        ]);

        $result = new NotificationDelivery($this->clock(), $this->factory($sender), $logger, $logs)
            ->deliver($this->notification(), new Collection([new Bin()]), $this->slot());

        self::assertSame(DeliveryResult::FAILED, $result);
    }

    public function testDeliverFailsWithoutSendingWhenThePhoneNumberIsNotVerified(): void
    {
        $log = new NotificationLog();
        $notification = $this->notification()->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000000')->setAttribute('status', 'pending'));

        $logs = $this->createMock(NotificationLogRepositoryInterface::class);
        $logs->method('claim')->willReturn($log);
        $logs->expects($this->once())->method('markFailed')->with($log, 'The phone number is not verified.');
        $logs->expects($this->never())->method('markSent');

        $factory = $this->createMock(NotificationSenderFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Notification delivery failed.', [
            'notification' => 'n1',
            'reason' => 'The phone number is not verified.',
            'scheduled_for' => '1985-10-26 01:30',
        ]);

        $result = new NotificationDelivery($this->clock(), $factory, $logger, $logs)->deliver($notification, new Collection([new Bin()]), $this->slot());

        self::assertSame(DeliveryResult::FAILED, $result);
    }

    #[DataProvider('getUnclaimedStatuses')]
    public function testDeliverSendsNothingWhenTheSlotIsNotOursToTake(DeliveryStatus $status, DeliveryResult $expected): void
    {
        $logs = $this->createMock(NotificationLogRepositoryInterface::class);
        $logs->method('claim')->willReturn(null);
        $logs->expects($this->once())->method('statusFor')->with($this->notification(), $this->slot())->willReturn($status);
        $logs->expects($this->never())->method('markFailed');
        $logs->expects($this->never())->method('markSent');

        $factory = $this->createMock(NotificationSenderFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $result = new NotificationDelivery($this->clock(), $factory, $this->createStub(LoggerInterface::class), $logs)
            ->deliver($this->notification(), new Collection([new Bin()]), $this->slot());

        self::assertSame($expected, $result);
    }

    private function clock(): KnownClock
    {
        return new KnownClock($this->now());
    }

    private function factory(NotificationSenderInterface $sender): NotificationSenderFactoryInterface
    {
        $factory = $this->createMock(NotificationSenderFactoryInterface::class);
        $factory->expects($this->once())->method('create')->with(NotificationType::SMS)->willReturn($sender);

        return $factory;
    }

    private function notification(): Notification
    {
        return new Notification()
            ->setAttribute('id', 'n1')
            ->setAttribute('type', 'sms')
            ->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000000')->setAttribute('status', 'verified'));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('1985-10-26 01:31:07', new DateTimeZone('UTC'));
    }

    private function slot(): DateTimeImmutable
    {
        return new DateTimeImmutable('1985-10-26 01:30', new DateTimeZone('UTC'));
    }
}
