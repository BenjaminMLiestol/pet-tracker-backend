<?php

namespace App\Http\Controllers;

use App\Models\Vaccination;
use Illuminate\Http\Request;

class VaccinationController extends Controller
{
    public function index(Request $request)
    {
        $query = Vaccination::with(['pet', 'user'])->orderByDesc('given_at');
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->integer('pet_id'));
        }

        return $query->paginate(20);
    }

    public function show(Vaccination $vaccination)
    {
        return $vaccination->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'name' => 'required|string|max:255',
            'given_at' => 'nullable|date',
            'next_due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['user_id'] = $request->user()->id;
        $vaccination = Vaccination::create($data);

        return response()->json($vaccination->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Vaccination $vaccination)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'given_at' => 'sometimes|date',
            'next_due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $vaccination->update($data);

        return $vaccination->load(['pet', 'user']);
    }

    public function destroy(Vaccination $vaccination)
    {
        $vaccination->delete();

        return response()->json([], 204);
    }
}
