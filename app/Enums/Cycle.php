<?php

declare(strict_types=1);

namespace App\Enums;

enum Cycle: string
{
    case FORTNIGHTLY = 'fortnightly';

    case MONTHLY = 'monthly';

    case WEEKLY = 'weekly';
}
