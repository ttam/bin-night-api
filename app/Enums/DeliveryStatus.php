<?php

declare(strict_types=1);

namespace App\Enums;

/** Where one notification's delivery for one slot has got to. A slot that is `processing` has been claimed. */
enum DeliveryStatus: string
{
    case FAILED = 'failed';
    case PROCESSING = 'processing';
    case SENT = 'sent';
}
