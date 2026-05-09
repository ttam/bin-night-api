<?php

declare(strict_types=1);

namespace App\Services\Notifications\Twilio\Interfaces;

interface TwilioServiceInterface
{
    public function sendMessage(string $to, string $message): bool;
}
