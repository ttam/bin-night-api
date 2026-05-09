<?php

declare(strict_types=1);

namespace App\Services\Notifications\Twilio;

use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;

final readonly class TwilioDumper implements TwilioServiceInterface
{
    /** @codeCoverageIgnore */
    public function sendMessage(string $to, string $message): bool
    {
        \dump(\sprintf('Sending message "%s" to "%s"', $message, $to));
        return true;
    }
}
