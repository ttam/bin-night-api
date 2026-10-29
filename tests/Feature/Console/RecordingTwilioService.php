<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Notifications\Exceptions\DeliveryFailedException;
use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;

/** Stands in for Twilio: keeps what it was asked to send, and can be told to fail. */
final class RecordingTwilioService implements TwilioServiceInterface
{
    public ?DeliveryFailedException $failure = null;

    /** @var list<array{message: string, to: string}> */
    public array $messages = [];

    public function sendMessage(string $to, string $message): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->messages[] = ['message' => $message, 'to' => $to];
    }
}
