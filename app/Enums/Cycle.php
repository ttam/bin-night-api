<?php

declare(strict_types=1);

namespace App\Enums;

enum Cycle: string
{
    case FORTNIGHTLY = 'fortnightly';

    case FOUR_WEEKLY = 'four-weekly';

    case MONTHLY = 'monthly';

    case WEEKLY = 'weekly';

    /** Weeks between collections, or null when the cycle is not a fixed number of weeks (monthly). */
    public function weeks(): ?int
    {
        return match ($this) {
            self::FORTNIGHTLY => 2,

            self::FOUR_WEEKLY => 4,

            self::MONTHLY => null,

            self::WEEKLY => 1,
        };
    }
}
