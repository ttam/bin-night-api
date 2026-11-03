<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\VerificationResult;
use App\Services\Verification\Exceptions\VerificationUnavailableException;
use App\Services\Verification\Interfaces\PhoneVerifierInterface;
use Illuminate\Config\Repository;
use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/** Twilio Verify. Twilio holds the code, its expiry and the limit on guesses, so none of it is stored here. */
final readonly class TwilioVerifier implements PhoneVerifierInterface
{
    /** Twilio's "max check attempts reached". */
    private const int TOO_MANY_ATTEMPTS = 60202;

    private Client $client;

    public function __construct(private Repository $config, ?Client $client = null)
    {
        $this->client = $client ?? new Client(
            $config->string('services.twilio.sid'),
            $config->string('services.twilio.secret'),
        );
    }

    public function check(string $number, string $code): VerificationResult
    {
        try {
            $check = $this->client->verify->v2->services($this->config->string('services.twilio.verify_sid'))
                ->verificationChecks->create(['to' => $number, 'code' => $code]);
        } catch (RestException $e) {
            // A verification Twilio no longer has (it expired, was approved already, or never existed) is a 404.
            return match (true) {
                $e->getStatusCode() === 404 => VerificationResult::EXPIRED,
                $e->getCode() === self::TOO_MANY_ATTEMPTS => VerificationResult::TOO_MANY_ATTEMPTS,
                default => VerificationResult::UNAVAILABLE,
            };
        } catch (TwilioException) {
            return VerificationResult::UNAVAILABLE;
        }

        return $check->status === 'approved' ? VerificationResult::APPROVED : VerificationResult::WRONG_CODE;
    }

    public function enrol(string $number): void
    {
        try {
            $this->client->verify->v2->services($this->config->string('services.twilio.verify_sid'))
                ->verifications->create($number, 'sms');
        } catch (TwilioException $e) {
            // Twilio's own message can contain the phone number, so only the class and its error code are kept.
            throw new VerificationUnavailableException(\sprintf('%s (code %d)', $e::class, $e->getCode()), previous: $e);
        }
    }
}
