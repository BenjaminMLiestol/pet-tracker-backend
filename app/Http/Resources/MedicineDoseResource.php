<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineDoseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medicine_id' => $this->medicine_id,
            // The due day and time slot this dose was given for.
            'for' => $this->given_for->toDateString(),
            'slot' => $this->slot,
            'given_at' => $this->given_at,
            'user_id' => $this->user_id,
        ];
    }
}
