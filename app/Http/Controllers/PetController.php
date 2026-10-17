<?php

// app/Http/Controllers/PetController.php

namespace App\Http\Controllers;

use App\Http\Resources\PetResource;
use App\Models\Pet;
use Illuminate\Http\Request;

class PetController extends Controller
{
    public function index(Request $request)
    {
        return Pet::ownedBy($request->user())->with('users')->paginate(20)
            ->through(fn ($pet) => new PetResource($pet));
    }

    public function show(Request $request, Pet $pet)
    {
        $this->authorizePet($request, $pet);

        return new PetResource($pet->load('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'breed' => 'nullable|string|max:255',
            'timezone' => 'sometimes|timezone:all',
            'bath_interval_days' => 'sometimes|integer|min:1|max:365',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
        ]);
        $pet = Pet::create($data);
        // The creator is the first owner. Others join through an invitation code.
        $pet->users()->attach($request->user()->id);

        return (new PetResource($pet->load('users')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Pet $pet)
    {
        $this->authorizePet($request, $pet);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'breed' => 'sometimes|string|nullable|max:255',
            'timezone' => 'sometimes|timezone:all',
            'bath_interval_days' => 'sometimes|integer|min:1|max:365',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
        ]);
        $pet->update($data);

        return new PetResource($pet->load('users'));
    }

    public function destroy(Request $request, Pet $pet)
    {
        $this->authorizePet($request, $pet);

        $pet->delete();

        return response()->json([], 204);
    }

    private function authorizePet(Request $request, Pet $pet): void
    {
        abort_unless(Pet::ownedBy($request->user())->whereKey($pet->id)->exists(), 404);
    }
}
