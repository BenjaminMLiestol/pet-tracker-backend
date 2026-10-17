<?php

namespace App\Http\Controllers;

use App\Models\ParasiteTreatment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParasiteTreatmentController extends Controller
{
    public function index(Request $request)
    {
        $query = ParasiteTreatment::with(['pet', 'user'])->orderByDesc('given_at');
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->integer('pet_id'));
        }

        return $query->paginate(20);
    }

    public function show(ParasiteTreatment $parasiteTreatment)
    {
        return $parasiteTreatment->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'type' => ['required', Rule::in(['flea', 'tick', 'worm'])],
            'product' => 'nullable|string|max:255',
            'given_at' => 'nullable|date',
            'next_due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['user_id'] = $request->user()->id;
        $parasiteTreatment = ParasiteTreatment::create($data);

        return response()->json($parasiteTreatment->load(['pet', 'user']), 201);
    }

    public function update(Request $request, ParasiteTreatment $parasiteTreatment)
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['flea', 'tick', 'worm'])],
            'product' => 'nullable|string|max:255',
            'given_at' => 'sometimes|date',
            'next_due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $parasiteTreatment->update($data);

        return $parasiteTreatment->load(['pet', 'user']);
    }

    public function destroy(ParasiteTreatment $parasiteTreatment)
    {
        $parasiteTreatment->delete();

        return response()->json([], 204);
    }
}
