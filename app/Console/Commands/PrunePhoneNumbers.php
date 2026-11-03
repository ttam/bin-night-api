<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Interfaces\PhoneNumberRepositoryInterface;
use Illuminate\Console\Command;
use Psr\Clock\ClockInterface;

final class PrunePhoneNumbers extends Command
{
    protected $description = 'Delete phone numbers that were never verified.';

    protected $signature = 'bin-night:prune-phone-numbers';

    public function handle(ClockInterface $clock, PhoneNumberRepositoryInterface $repository): int
    {
        $hours = \config()->integer('phone.prune_after_hours');

        $this->info(\sprintf('Deleted %d unverified phone numbers.', $repository->pruneUnverified($clock->now()->modify(\sprintf('-%d hours', $hours)))));

        return self::SUCCESS;
    }
}
