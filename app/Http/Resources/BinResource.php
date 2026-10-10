<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property \App\Models\Bin $resource */
final class BinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bin_color' => \sprintf('#%s', $this->resource->binColor),
            'cycle' => $this->resource->cycle->value,
            'id' => $this->resource->getKey(),
            'lid_color' => \sprintf('#%s', $this->resource->lidColor),
            'name' => $this->resource->name,
            'next_bin_night' => $this->resource->computedNextBinNight?->format(DateTimeInterface::ATOM),
        ];
    }
}
