<?php

namespace App\Http\Controllers;

use App\Models\VetVisit;
use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use Illuminate\Http\Request;

class VetVisitController extends Controller
{
    use AuthorizesPetAccess;

    public function index(Request $request)
    {
        $query = VetVisit::with(['pet', 'user'])->orderByDesc('visited_at');
        $this->scopeToUserPets($query, $request);

        return $query->paginate(20);
    }

    public function show(Request $request, VetVisit $vetVisit)
    {
        $this->authorizeRecord($request, $vetVisit);
        return $vetVisit->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => $this->petIdRule($request),
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
        $this->authorizeRecord($request, $vetVisit);
        $data = $request->validate([
            'reason' => 'sometimes|string|max:255',
            'visited_at' => 'sometimes|date',
            'next_visit_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $vetVisit->update($data);

        return $vetVisit->load(['pet', 'user']);
    }

    public function destroy(Request $request, VetVisit $vetVisit)
    {
        $this->authorizeRecord($request, $vetVisit);
        $vetVisit->delete();

        return response()->json([], 204);
    }
}
