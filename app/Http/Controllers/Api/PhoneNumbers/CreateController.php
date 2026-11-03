<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\PhoneNumbers;

use App\Auth\AuthenticatedUser;
use App\Enums\PhoneNumberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PhoneNumber\CreatePhoneNumberRequest;
use App\Http\Resources\PhoneNumberResource;
use App\Repositories\Interfaces\PhoneNumberRepositoryInterface;
use App\Services\Verification\Interfaces\CodeSenderInterface;

/** Idempotent: a number the user has already verified comes straight back, and no code is sent. */
final class CreateController extends Controller
{
    public function __invoke(CreatePhoneNumberRequest $request, PhoneNumberRepositoryInterface $repository, CodeSenderInterface $codes): PhoneNumberResource
    {
        $phoneNumber = $repository->findOrCreate(AuthenticatedUser::fromRequest($request), $request->string('number')->toString());

        if ($phoneNumber->status === PhoneNumberStatus::PENDING) {
            $codes->send($phoneNumber, (string) $request->ip());
        }

        return new PhoneNumberResource($phoneNumber);
    }
}
