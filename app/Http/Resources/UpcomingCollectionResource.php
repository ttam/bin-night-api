<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Bin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property \App\DTO\Schedule\UpcomingCollection $resource */
final class UpcomingCollectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'bins' => $this->resource->bins->sortBy(static fn (Bin $bin): string => $bin->name, \SORT_NATURAL | \SORT_FLAG_CASE)->values()->map(static fn (Bin $bin): array => [
                'bin_color' => \sprintf('#%s', $bin->binColor),
                'id' => $bin->getKey(),
                'lid_color' => \sprintf('#%s', $bin->lidColor),
                'name' => $bin->name,
            ])->all(),
            'date' => $this->resource->date->format('Y-m-d'),
            'house_id' => $this->resource->house->getKey(),
        ];
    }
}
