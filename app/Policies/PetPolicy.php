<?php

namespace App\Policies;

use App\Models\Pet;
use App\Models\User;

class PetPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function write(User $user, Pet $pet): bool
    {
        return $pet->users()->where('users.id', $user->id)->exists();
    }
}
