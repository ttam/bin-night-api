<?php

declare(strict_types=1);

namespace App\Http\Requests\House;

final class CreateHouseRequest extends AbstractHouseRequest
{
    protected string $presence {
        get {
            return 'required';
        }
    }
}
