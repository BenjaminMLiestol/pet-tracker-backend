<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvitationResource;
use App\Http\Resources\PetResource;
use App\Models\Pet;
use App\Models\PetInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PetInvitationController extends Controller
{
    /** Active invitations for a dog (owners only). */
    public function index(Request $request, Pet $pet)
    {
        $this->authorizeOwner($request, $pet);

        return InvitationResource::collection($pet->invitations()->active()->latest()->get());
    }

    /** Create a one-time code that lets someone else become a co-owner. */
    public function store(Request $request, Pet $pet)
    {
        $this->authorizeOwner($request, $pet);

        if ($pet->invitations()->active()->count() >= PetInvitation::MAX_ACTIVE) {
            throw ValidationException::withMessages([
                'pet' => 'Too many invitations are open for this dog. Cancel one first.',
            ]);
        }

        do {
            $code = PetInvitation::generateCode();
        } while (PetInvitation::where('code', $code)->exists());

        $invitation = $pet->invitations()->create([
            'invited_by' => $request->user()->id,
            'code' => $code,
            'expires_at' => now()->addDays(PetInvitation::TTL_DAYS),
        ]);

        return (new InvitationResource($invitation))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Pet $pet, PetInvitation $invitation)
    {
        $this->authorizeOwner($request, $pet);
        abort_unless($invitation->pet_id === $pet->id, 404);

        $invitation->update(['revoked_at' => now()]);

        return response()->json([], 204);
    }

    /** Use a code: the signed-in user becomes a co-owner of that dog. */
    public function accept(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:32']);
        $user = $request->user();

        $pet = DB::transaction(function () use ($data, $user) {
            $invitation = PetInvitation::active()
                ->where('code', PetInvitation::normalize($data['code']))
                ->lockForUpdate()
                ->first();

            // One message for every failure, so codes can't be probed.
            $invalid = ValidationException::withMessages([
                'code' => "That code isn't valid or has expired.",
            ]);
            if (! $invitation || ! ($pet = $invitation->pet)) {
                throw $invalid;
            }

            if ($pet->users()->whereKey($user->id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'You already look after this dog.',
                ]);
            }

            $pet->users()->attach($user->id);
            $invitation->update(['accepted_by' => $user->id, 'accepted_at' => now()]);

            return $pet;
        });

        return new PetResource($pet->load('users'));
    }

    private function authorizeOwner(Request $request, Pet $pet): void
    {
        abort_unless(Pet::ownedBy($request->user())->whereKey($pet->id)->exists(), 404);
    }
}
