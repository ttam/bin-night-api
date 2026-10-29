<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\AuthenticatedUser;
use App\Enums\DeliveryResult;
use App\Models\Bin;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Notifications\Interfaces\NotificationDeliveryInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Psr\Clock\ClockInterface;

#[Description('Trigger notifications')]

#[Signature('
    bin-night:trigger-notifications
        {now? : Optional override for current UTC time, e.g. "1985-10-26 01:21"}
        {--dry : Log what would be sent without claiming or sending anything}
')]

class TriggerNotifications extends Command
{
    private const int EXIT_CODE_BAD_INPUT = 5;

    private const int EXIT_CODE_DELIVERY_FAILED = 1;

    private const int EXIT_CODE_OK = 0;

    public function handle(
        ActiveEntityRepositoryInterface $activeEntityRepository,
        BinRepositoryInterface $binRepository,
        ClockInterface $clock,
        Repository $config,
        NotificationDeliveryInterface $delivery,
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

        // A time given on the command line is an instruction to run that slot, so only a scheduled run catches up.
        $slots = $this->slots($dateTime, $now === null ? \max(0, $config->integer('notifications.catch_up_hours')) : 0);

        /** @var list<array{0: DateTimeImmutable, 1: \App\Models\Notification}> $due */
        $due = [];

        foreach ($slots as $slot) {
            foreach ($notificationRepository->getDueNotifications($slot) as $notification) {
                $due[] = [$slot, $notification];
            }
        }

        /** @var array<string, \App\Services\Plan\ActiveEntities> $activeEntities */
        $activeEntities = [];

        $results = \array_fill_keys(\array_map(static fn (DeliveryResult $result): string => $result->value, DeliveryResult::cases()), 0);
        $skipped = 0;
        $wouldSend = 0;

        foreach ($due as [$slot, $notification]) {
            // Changing a notification after the slot does not entitle it to the slot: someone who moves a notification
            // to 18:30 at 18:50 should not be sent a message for 18:30 at 19:00.
            $updatedAt = $notification->updatedAt;

            if ($updatedAt !== null && $updatedAt > $slot) {
                $skipped++;
                continue;
            }

            $user = new AuthenticatedUser(['sub' => $notification->userExternalId]);
            $userEntities = $activeEntities[$user->id] ??= $activeEntityRepository->getActiveEntitiesForUser($user);

            if ($userEntities->isNotificationActive($notification) === false) {
                $skipped++;
                continue;
            }

            $bins = $binRepository
                ->getBinsForNotification($notification, $slot)
                ->filter(static fn (Bin $bin): bool => $userEntities->isBinActive($bin))
                ->values();

            if ($bins->isEmpty()) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $wouldSend++;
                $this->info(\sprintf(
                    '<fg=yellow>[DRY] Would send to "%s" with %d bins (%s)</>',
                    $notification->target,
                    \count($bins),
                    \implode(', ', $bins->map(static fn (Bin $bin): string => $bin->name)->all()),
                ));
                continue;
            }

            $results[$delivery->deliver($notification, $bins, $slot)->value]++;
        }

        $label = $this->label($slots);

        return $dryRun
            ? $this->reportDryRun($label, \count($due), $skipped, $wouldSend)
            : $this->report($label, \count($due), $skipped, $results);
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

    /** @param non-empty-list<DateTimeImmutable> $slots */
    private function label(array $slots): string
    {
        $first = $slots[0]->format('Y-m-d H:i');
        $last = $slots[\array_key_last($slots)]->format('Y-m-d H:i');

        return $first === $last ? $first : \sprintf('%s to %s', $first, $last);
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

    /** @param array<string, int> $results */
    private function report(string $label, int $due, int $skipped, array $results): int
    {
        $this->info(\sprintf(
            '%s: %d due, %d skipped, %d sent, %d failed, %d already sent, %d in progress.',
            $label,
            $due,
            $skipped,
            $results[DeliveryResult::SENT->value],
            $results[DeliveryResult::FAILED->value],
            $results[DeliveryResult::ALREADY_SENT->value],
            $results[DeliveryResult::IN_PROGRESS->value],
        ));

        if ($results[DeliveryResult::IN_PROGRESS->value] > 0) {
            $this->warn('Some notifications are being sent by another run, or an earlier run was interrupted. Rows in notification_logs that stay "processing" need checking by hand: they are never retried.');
        }

        if ($results[DeliveryResult::FAILED->value] > 0) {
            $this->error('Some notifications failed. Run this command again for the same slot to retry them.');

            return self::EXIT_CODE_DELIVERY_FAILED;
        }

        return self::EXIT_CODE_OK;
    }

    private function reportDryRun(string $label, int $due, int $skipped, int $wouldSend): int
    {
        $this->info(\sprintf('[DRY] %s: %d due, %d skipped, %d would be sent.', $label, $due, $skipped, $wouldSend));

        return self::EXIT_CODE_OK;
    }

    /**
     * The slot itself and the half hours before it that fall inside the catch-up window, oldest first so that the
     * messages go out in the order they were due.
     *
     * @return non-empty-list<DateTimeImmutable>
     */
    private function slots(DateTimeImmutable $slot, int $catchUpHours): array
    {
        return \array_map(
            static fn (int $back): DateTimeImmutable => $slot->modify(\sprintf('-%d minutes', $back * 30)),
            \range($catchUpHours * 2, 0),
        );
    }
}
