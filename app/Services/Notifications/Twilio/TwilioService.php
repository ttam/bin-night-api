<?php

declare(strict_types=1);

namespace App\Services\Notifications\Twilio;

use App\Services\Notifications\Exceptions\DeliveryFailedException;
use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;
use Illuminate\Config\Repository;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

final readonly class TwilioService implements TwilioServiceInterface
{
    private Client $client;

    public function __construct(private Repository $config, ?Client $client = null)
    {
        $this->client = $client ?? new Client(
            $config->string('services.twilio.sid'),
            $config->string('services.twilio.secret'),
        );
    }

    public function sendMessage(string $to, string $message): void
    {
        try {
            $this->client->messages->create($to, [
                'from' => $this->config->get('services.twilio.phone_number'),
                'body' => $message,
            ]);
        } catch (TwilioException $e) {
            // Twilio's own message can contain the phone number, so only the class and its error code are kept.
            throw new DeliveryFailedException(\sprintf('%s (code %d)', $e::class, $e->getCode()), previous: $e);
        }
    }
}
