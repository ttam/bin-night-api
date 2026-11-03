<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\PhoneNumbers;

use App\Enums\PhoneNumberStatus;
use App\Enums\VerificationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\PhoneNumber\VerifyPhoneNumberRequest;
use App\Http\Resources\PhoneNumberResource;
use App\Models\PhoneNumber;
use App\Repositories\Interfaces\PhoneNumberRepositoryInterface;
use App\Services\Verification\Interfaces\PhoneVerifierInterface;
use Illuminate\Http\JsonResponse;
use Psr\Clock\ClockInterface;

final class VerifyController extends Controller
{
    public function __invoke(
        VerifyPhoneNumberRequest $request,
        ClockInterface $clock,
        PhoneNumberRepositoryInterface $repository,
        PhoneVerifierInterface $verifier,
        PhoneNumber $phoneNumber,
    ): JsonResponse|PhoneNumberResource {
        if ($phoneNumber->status === PhoneNumberStatus::VERIFIED) {
            return new PhoneNumberResource($phoneNumber);
        }

        $result = $verifier->check($phoneNumber->number, $request->string('code')->toString());

        if ($result !== VerificationResult::APPROVED) {
            return new JsonResponse(['message' => $result->message(), 'errors' => ['code' => [$result->message()]]], 422);
        }

        return new PhoneNumberResource($repository->markVerified($phoneNumber, $clock->now()));
    }
}
