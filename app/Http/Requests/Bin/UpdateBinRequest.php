<?php

declare(strict_types=1);

namespace App\Http\Requests\Bin;

final class UpdateBinRequest extends AbstractBinRequest
{
    protected string $presence {
        get {
            return 'sometimes';
        }
    }
}
