<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\DTO\Bin\UpdateBin;
use App\Models\Bin;
use App\Models\Notification;
use DateTimeImmutable;
use Illuminate\Support\Collection;

interface BinRepositoryInterface
{
    public function createBin(AuthenticatedUser $authenticatedUser, CreateBin $data): Bin;

    /** @return \Illuminate\Support\Collection<int, \App\Models\Bin> */
    public function getBinsForNotification(Notification $notification, DateTimeImmutable $now): Collection;

    /** @return \Illuminate\Support\Collection<int, \App\Models\Bin> */
    public function getBinsForUser(AuthenticatedUser $user): Collection;

    public function updateBin(Bin $bin, UpdateBin $data): Bin;
}
