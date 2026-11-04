<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PhoneNumberStatus;

/** A mobile number owned by a user. Once verified it serves every notification in every house. */
final class PhoneNumber extends Model
{
    public string $number {
        get => $this->getAttribute('number');
    }

    public PhoneNumberStatus $status {
        get => $this->getAttribute('status');
    }

    /** The primary key, typed. (Not called `id`: Eloquent reads that while it is still being made.) */
    public string $uuid {
        get => $this->getAttribute('id');
    }

    protected function casts(): array
    {
        return [
            'status' => PhoneNumberStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
