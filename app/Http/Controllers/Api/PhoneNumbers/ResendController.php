<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\PhoneNumbers;

use App\Enums\PhoneNumberStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneNumberResource;
use App\Models\PhoneNumber;
use App\Services\Verification\Interfaces\CodeSenderInterface;
use Illuminate\Http\Request;

final class ResendController extends Controller
{
    public function __invoke(Request $request, CodeSenderInterface $codes, PhoneNumber $phoneNumber): PhoneNumberResource
    {
        if ($phoneNumber->status === PhoneNumberStatus::PENDING) {
            $codes->send($phoneNumber, (string) $request->ip());
        }

        return new PhoneNumberResource($phoneNumber);
    }
}
