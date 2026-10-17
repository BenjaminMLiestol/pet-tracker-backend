<?php

namespace App\Http\Controllers;

use App\Models\VetVisit;
use Illuminate\Http\Request;

class VetVisitController extends Controller
{
    public function index(Request $request)
    {
        $query = VetVisit::with(['pet', 'user'])->orderByDesc('visited_at');
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->integer('pet_id'));
        }

        return $query->paginate(20);
    }

    public function show(VetVisit $vetVisit)
    {
        return $vetVisit->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'reason' => 'required|string|max:255',
            'visited_at' => 'nullable|date',
            'next_visit_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['user_id'] = $request->user()->id;
        $vetVisit = VetVisit::create($data);

        return response()->json($vetVisit->load(['pet', 'user']), 201);
    }

    public function update(Request $request, VetVisit $vetVisit)
    {
        $data = $request->validate([
            'reason' => 'sometimes|string|max:255',
            'visited_at' => 'sometimes|date',
            'next_visit_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $vetVisit->update($data);

        return $vetVisit->load(['pet', 'user']);
    }

    public function destroy(VetVisit $vetVisit)
    {
        $vetVisit->delete();

        return response()->json([], 204);
    }
}
