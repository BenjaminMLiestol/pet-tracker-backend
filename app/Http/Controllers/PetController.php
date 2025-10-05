<?php

// app/Http/Controllers/PetController.php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Http\Request;

class PetController extends Controller
{
    public function index()
    {
        return Pet::with('users')->paginate(20);
    }

    public function show(Pet $pet)
    {
        return $pet->load('users');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'age' => 'nullable|integer',
            'breed' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);
        $pet = Pet::create($data);
        if (! empty($data['user_ids'])) {
            $pet->users()->sync($data['user_ids']);
        }

        return response()->json($pet->load('users'), 201);
    }

    public function update(Request $request, Pet $pet)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'age' => 'sometimes|integer|nullable',
            'breed' => 'sometimes|string|nullable',
            'date_of_birth' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);
        $pet->update($data);
        if (array_key_exists('user_ids', $data)) {
            $pet->users()->sync($data['user_ids'] ?? []);
        }

        return $pet->load('users');
    }

    public function destroy(Pet $pet)
    {
        $pet->delete();

        return response()->json([], 204);
    }
}
