<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'breed' => $this->breed,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            // Co-owners are shown by name only; emails and birth dates stay private.
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
