<?php

declare(strict_types=1);

namespace App\Enums;

enum Plan: string
{
    case FREE = 'free';

    case PLUS = 'plus';

    case UNLIMITED = 'unlimited';

    public function tiers(): int
    {
        return match ($this) {
            self::FREE => 1,
            self::PLUS => 2,
            self::UNLIMITED => 3,
        };
    }
}
