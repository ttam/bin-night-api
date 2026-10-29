<?php

declare(strict_types=1);

namespace App\Services\Notifications\Twilio\Interfaces;

interface TwilioServiceInterface
{
    /** @throws \App\Services\Notifications\Exceptions\DeliveryFailedException */
    public function sendMessage(string $to, string $message): void;
}
