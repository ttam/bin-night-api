<?php

declare(strict_types=1);

namespace App\Http\Requests\House;

final class UpdateHouseRequest extends AbstractHouseRequest
{
    protected string $presence {
        get {
            return 'sometimes';
        }
    }
}
