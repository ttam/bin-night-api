<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\AuthenticatedUser;
use App\Events\NotificationEvent;
use App\Models\Bin;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
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
        ActiveEntityRepositoryInterface $activeEntityRepository,
        BinRepositoryInterface $binRepository,
        ClockInterface $clock,
        Dispatcher $dispatcher,
        NotificationRepositoryInterface $notificationRepository,
    ): int {
        $dryRun = \boolval($this->option('dry'));
        $now = \is_string($argument = $this->argument('now')) ? $argument : null;

        $time = $now === null ? $clock->now()->setTimezone(new DateTimeZone('UTC')) : $this->parseDate($now);

        if ($time === null) {
            $this->error(\sprintf('Invalid date format "%s"', $now));
            return self::EXIT_CODE_BAD_INPUT;
        }

        $dateTime = $this->floorToSlot($time);

        $notifications = $notificationRepository->getDueNotifications($dateTime);

        /** @var array<string, \App\Services\Plan\ActiveEntities> $activeEntities */
        $activeEntities = [];

        foreach ($notifications as $notification) {
            $user = new AuthenticatedUser(['sub' => $notification->userExternalId]);
            $userEntities = $activeEntities[$user->id] ??= $activeEntityRepository->getActiveEntitiesForUser($user);

            if ($userEntities->isNotificationActive($notification) === false) {
                continue;
            }

            $bins = $binRepository
                ->getBinsForNotification($notification, $dateTime)
                ->filter(static fn (Bin $bin): bool => $userEntities->isBinActive($bin))
                ->values();

            if ($bins->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $this->info(\sprintf(
                    '<fg=yellow>[DRY] Would dispatch NotificationEvent for notification "%s" with %d bins (%s)</>',
                    $notification->target,
                    \count($bins),
                    \implode(', ', $bins->map(static fn (Bin $bin): string => $bin->name)->all()),
                ));
                continue;
            }

            $dispatcher->dispatch(new NotificationEvent($notification, $bins));
        }

        $this->info(\sprintf('Processed %d notifications for %s.', \count($notifications), $dateTime->format('Y-m-d H:i')));
        return self::EXIT_CODE_OK;
    }

    /**
     * Rounds a time down to the start of its half hour. The schedule runs every 30 minutes, but cron can start a little
     * late (11:31 rather than 11:30), and notifications are matched against their send time exactly, so without this
     * the 11:30 notifications would be missed.
     */
    private function floorToSlot(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTime(
            \intval($time->format('H')),
            \intdiv(\intval($time->format('i')), 30) * 30,
        );
    }

    /** Reads the `now` argument as a UTC time, or returns null if it is not a real date and time in `Y-m-d H:i` form. */
    private function parseDate(string $input): ?DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $input, new DateTimeZone('UTC'));

        // createFromFormat() rolls impossible dates over (31 February becomes 3 March). This command sends real
        // messages, so running the wrong day's slot silently is worse than refusing: the input has to read back exactly
        // as it was given.
        return $time !== false && $time->format('Y-m-d H:i') === $input ? $time : null;
    }
}
