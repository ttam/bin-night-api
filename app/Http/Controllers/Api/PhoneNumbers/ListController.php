<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\PhoneNumbers;

use App\Auth\AuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneNumberResource;
use App\Repositories\Interfaces\PhoneNumberRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListController extends Controller
{
    public function __invoke(Request $request, PhoneNumberRepositoryInterface $repository): AnonymousResourceCollection
    {
        return PhoneNumberResource::collection($repository->getPhoneNumbersForUser(AuthenticatedUser::fromRequest($request)));
    }
}
