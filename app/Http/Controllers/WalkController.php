<?php

// app/Http/Controllers/WalkController.php

namespace App\Http\Controllers;

use App\Models\Walk;
use Illuminate\Http\Request;

class WalkController extends Controller
{
    public function index(Request $request)
    {
        $query = Walk::with(['pet', 'user'])->orderByDesc('walked_at');
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->integer('pet_id'));
        }

        return $query->paginate(20);
    }

    public function show(Walk $walk)
    {
        return $walk->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'walk_check' => 'sometimes|boolean',
            'walked_at' => 'nullable|date',
        ]);
        $data['user_id'] = $request->user()->id;
        $walk = Walk::create($data + ['walk_check' => $data['walk_check'] ?? true]);

        return response()->json($walk->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Walk $walk)
    {
        $data = $request->validate([
            'walk_check' => 'sometimes|boolean',
            'walked_at' => 'sometimes|date',
        ]);
        $walk->update($data);

        return $walk->load(['pet', 'user']);
    }

    public function destroy(Walk $walk)
    {
        $walk->delete();

        return response()->json([], 204);
    }
}
