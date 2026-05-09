<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\NotificationEvent;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Clock\ClockInterface;

#[Description('Trigger notifications')]

#[Signature('
    bin-night:trigger-notifications
        {now? : Optional override for current UTC time, e.g. "1985-10-26 01:21"}
        {--dry : Log what would be dispatched without sending events}
')]

class TriggerNotifications extends Command
{
    private const int EXIT_CODE_OK = 0;

    private const int EXIT_CODE_BAD_INPUT = 5;

    public function handle(
        ClockInterface $clock,
        Dispatcher $dispatcher,
        BinRepositoryInterface $binRepository,
        NotificationRepositoryInterface $notificationRepository,
    ): int {
        $dryRun = \boolval($this->option('dry'));
        $now = $this->argument('now');

        // If $now is not an ISO-8601 string, return an error
        if ($now && (\is_string($now) === false || \preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $now) === 0)) {
            $this->error(\sprintf('Invalid date format "%s"', $now));
            return self::EXIT_CODE_BAD_INPUT;
        }

        $dateTime = $this->getDateTime($now ?? $clock->now()->format('Y-m-d H:i'));

        $notifications = $notificationRepository->getDueNotifications($dateTime);

        foreach ($notifications as $notification) {
            $bins = $binRepository->getBinsForNotification($notification, $dateTime);

            if ($bins->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $this->info(\sprintf(
                    '<fg=yellow>[DRY] Would dispatch NotificationEvent for notification "%s" with %d bins (%s)</>',
                    $notification->target,
                    \count($bins),
                    \implode(', ', $bins->pluck('name')->toArray()),
                ));
                continue;
            }

            $dispatcher->dispatch(new NotificationEvent($notification, $bins));
        }

        $this->info(\sprintf('Processed %d notifications for %s.', \count($notifications), $dateTime->format('Y-m-d H:i')));
        return self::EXIT_CODE_OK;
    }

    /**
     * Set the dateTime property to the current time (or provided time) rounded down to the
     * nearest 30 minutes. This allows for slight variations in the cron execution time.
     */
    private function getDateTime(string $dateTime): DateTimeImmutable
    {
        $currentTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $dateTime, new DateTimeZone('UTC'));
        $hours = \intval($currentTime->format('H'));
        $minutes = \intval($currentTime->format('i'));

        return $currentTime->setTime($hours, \intval(\floor($minutes / 30) * 30), 0);
    }
}
