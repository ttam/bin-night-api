<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\PhoneNumber;
use App\Services\Verification\Exceptions\VerificationUnavailableException;
use App\Services\Verification\Interfaces\CodeSenderInterface;
use App\Services\Verification\Interfaces\PhoneVerifierInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Config\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * The provider limits wrong guesses; these limits stop us texting people. They are per user, per number (so many
 * accounts cannot all text one phone) and per IP address, each over an hour.
 */
final readonly class CodeSender implements CodeSenderInterface
{
    private const int HOUR = 3600;

    public function __construct(
        private Repository $config,
        private RateLimiter $limiter,
        private LoggerInterface $logger,
        private PhoneVerifierInterface $verifier,
    ) {
    }

    public function send(PhoneNumber $phoneNumber, string $ip): void
    {
        $limits = [
            'ip:' . $ip => $this->config->integer('phone.code_limits.per_ip_per_hour'),
            'number:' . $phoneNumber->number => $this->config->integer('phone.code_limits.per_number_per_hour'),
            'user:' . $phoneNumber->userId => $this->config->integer('phone.code_limits.per_user_per_hour'),
        ];

        foreach ($limits as $key => $max) {
            if ($this->limiter->tooManyAttempts('phone-code:' . $key, $max)) {
                throw new TooManyRequestsHttpException($this->limiter->availableIn('phone-code:' . $key), 'Too many codes have been requested. Try again later.');
            }
        }

        foreach (\array_keys($limits) as $key) {
            $this->limiter->hit('phone-code:' . $key, self::HOUR);
        }

        try {
            $this->verifier->enrol($phoneNumber->number);
        } catch (VerificationUnavailableException $e) {
            $this->logger->warning('Could not send a verification code.', ['reason' => $e->getMessage()]);

            throw new ServiceUnavailableHttpException(null, 'We could not send a code just now. Try again in a moment.', $e);
        }
    }
}
