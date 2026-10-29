<?php

declare(strict_types=1);

namespace App\DTO\Bin;

use App\Enums\Cycle;
use App\Http\Requests\Bin\CreateBinRequest;

final readonly class CreateBin
{
    public function __construct(
        public string $binColor,
        public Cycle $cycle,
        public string $houseId,
        public string $lidColor,
        public string $name,
        public string $startDate,
    ) {
    }

    public static function fromRequest(CreateBinRequest $request): self
    {
        return new self(
            \ltrim($request->string('bin_color')->toString(), '#'),
            Cycle::from($request->string('cycle')->toString()),
            $request->string('house_id')->toString(),
            \ltrim($request->string('lid_color')->toString(), '#'),
            $request->string('name')->toString(),
            $request->string('start_date')->toString(),
        );
    }
}
