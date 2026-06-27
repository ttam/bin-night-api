<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property \App\Models\House $resource */
final class HouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bin_night' => $this->resource->binNight,
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'timezone' => $this->resource->timezone->getName(),
        ];
    }
}
