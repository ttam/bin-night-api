<?php

declare(strict_types=1);

namespace App\Enums;

/** What an attempt to deliver a notification for a slot came to. */
enum DeliveryResult: string
{
    case ALREADY_SENT = 'already_sent';
    case FAILED = 'failed';
    case IN_PROGRESS = 'in_progress';
    case SENT = 'sent';
}
