<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\DeliveryResult;
use App\Enums\DeliveryStatus;
use App\Factories\Interfaces\NotificationSenderFactoryInterface;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationLogRepositoryInterface;
use App\Services\Notifications\Exceptions\DeliveryFailedException;
use App\Services\Notifications\Interfaces\NotificationDeliveryInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class NotificationDelivery implements NotificationDeliveryInterface
{
    public function __construct(
        private ClockInterface $clock,
        private NotificationSenderFactoryInterface $factory,
        private LoggerInterface $logger,
        private NotificationLogRepositoryInterface $logs,
    ) {
    }

    /**
     * The slot is claimed before anything is sent, so two runs cannot both send it. If the process dies after the claim
     * the slot stays `processing` and is never sent again: that may cost one message, but it can never cost a duplicate.
     */
    public function deliver(Notification $notification, Collection $bins, DateTimeImmutable $scheduledFor): DeliveryResult
    {
        $log = $this->logs->claim($notification, $scheduledFor);

        if ($log === null) {
            return $this->logs->statusFor($notification, $scheduledFor) === DeliveryStatus::SENT
                ? DeliveryResult::ALREADY_SENT
                : DeliveryResult::IN_PROGRESS;
        }

        try {
            $this->factory->create($notification->type)->send($notification->target, $bins);
        } catch (DeliveryFailedException $e) {
            $this->logs->markFailed($log, $e->getMessage());
            $this->logger->warning('Notification delivery failed.', [
                'notification' => $notification->getKey(),
                'reason' => $e->getMessage(),
                'scheduled_for' => $scheduledFor->format('Y-m-d H:i'),
            ]);

            return DeliveryResult::FAILED;
        }

        $this->logs->markSent($log, $this->clock->now());

        return DeliveryResult::SENT;
    }
}
