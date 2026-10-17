<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PetOwnerController extends Controller
{
    /**
     * Remove an owner from a dog: leave it yourself, or remove a co-owner. Any owner may do
     * this, but a dog always keeps at least one owner (delete the dog to get rid of it).
     */
    public function destroy(Request $request, Pet $pet, User $user)
    {
        abort_unless(Pet::ownedBy($request->user())->whereKey($pet->id)->exists(), 404);
        abort_unless($pet->users()->whereKey($user->id)->exists(), 404);

        if ($pet->users()->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'A dog needs at least one owner. Delete the dog instead.',
            ]);
        }

        $pet->users()->detach($user->id);

        return response()->json([], 204);
    }
}
