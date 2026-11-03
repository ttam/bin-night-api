<?php

declare(strict_types=1);

namespace App\Services\Verification\Interfaces;

use App\Models\PhoneNumber;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

interface CodeSenderInterface
{
    /**
     * Ask the provider to text a code, unless one of our limits says it has been asked for too often.
     *
     * @throws TooManyRequestsHttpException
     * @throws ServiceUnavailableHttpException
     */
    public function send(PhoneNumber $phoneNumber, string $ip): void;
}
