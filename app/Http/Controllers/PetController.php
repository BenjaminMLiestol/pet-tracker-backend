<?php

// app/Http/Controllers/PetController.php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PetController extends Controller
{
    public function index(Request $request)
    {
        return Pet::ownedBy($request->user())->with('users')->paginate(20);
    }

    public function show(Request $request, Pet $pet)
    {
        $this->authorizePet($request, $pet);

        return $pet->load('users');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'breed' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);
        $pet = Pet::create(Arr::except($data, 'user_ids'));
        $pet->users()->sync($this->ownerIds($request, $data['user_ids'] ?? []));

        return response()->json($pet->load('users'), 201);
    }

    public function update(Request $request, Pet $pet)
    {
        $this->authorizePet($request, $pet);

        $data = $request->validate([
            'name' => 'sometimes|string',
            'breed' => 'sometimes|string|nullable',
            'date_of_birth' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);
        $pet->update(Arr::except($data, 'user_ids'));
        if (array_key_exists('user_ids', $data)) {
            $pet->users()->sync($this->ownerIds($request, $data['user_ids'] ?? []));
        }

        return $pet->load('users');
    }

    public function destroy(Request $request, Pet $pet)
    {
        $this->authorizePet($request, $pet);

        $pet->delete();

        return response()->json([], 204);
    }

    /** The acting user is always an owner, so a pet can never be orphaned. */
    private function ownerIds(Request $request, array $userIds): array
    {
        return array_values(array_unique([...$userIds, $request->user()->id]));
    }

    private function authorizePet(Request $request, Pet $pet): void
    {
        abort_unless(Pet::ownedBy($request->user())->whereKey($pet->id)->exists(), 404);
    }
}
