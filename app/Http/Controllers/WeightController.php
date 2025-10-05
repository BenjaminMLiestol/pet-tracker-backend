<?php

// app/Http/Controllers/WeightController.php

namespace App\Http\Controllers;

use App\Models\Weight;
use Illuminate\Http\Request;

class WeightController extends Controller
{
    public function index(Request $request)
    {
        $query = Weight::with(['pet', 'user'])->orderByDesc('weighed_at');
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->integer('pet_id'));
        }

        return $query->paginate(20);
    }

    public function show(Weight $weight)
    {
        return $weight->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'value' => 'required|numeric',
            'weighed_at' => 'nullable|date',
        ]);
        $data['user_id'] = $request->user()->id;
        $weight = Weight::create($data);

        return response()->json($weight->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Weight $weight)
    {
        $data = $request->validate([
            'value' => 'sometimes|numeric',
            'weighed_at' => 'sometimes|date',
        ]);
        $weight->update($data);

        return $weight->load(['pet', 'user']);
    }

    public function destroy(Weight $weight)
    {
        $weight->delete();

        return response()->json([], 204);
    }
}
