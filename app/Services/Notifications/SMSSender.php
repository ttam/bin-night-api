<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Bin;
use App\Services\Notifications\Interfaces\NotificationSenderInterface;
use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;
use Illuminate\Support\Collection;

final readonly class SMSSender implements NotificationSenderInterface
{
    public function __construct(private TwilioServiceInterface $service)
    {
    }

    /** @inheritDoc */
    public function send(string $target, Collection $bins): bool
    {
        return $this->service->sendMessage($target, $bins->map(static fn (Bin $bin): string => $bin->name)->join(', '));
    }
}
