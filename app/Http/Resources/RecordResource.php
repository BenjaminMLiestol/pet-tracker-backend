<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Per-pet record (feeding, walk, bath, weight, care entry): the model's own
 * columns, never relations or soft-delete bookkeeping.
 */
class RecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return collect($this->resource->attributesToArray())
            ->except(['deleted_at'])
            ->all();
    }
}
