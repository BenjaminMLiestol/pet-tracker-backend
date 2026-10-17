<?php

namespace App\Http\Controllers;

use App\Models\Vaccination;
use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use Illuminate\Http\Request;

class VaccinationController extends Controller
{
    use AuthorizesPetAccess;

    public function index(Request $request)
    {
        $query = Vaccination::with(['pet', 'user'])->orderByDesc('given_at');
        $this->scopeToUserPets($query, $request);

        return $this->paginateRecords($query, $request, 'given_at');
    }

    public function show(Request $request, Vaccination $vaccination)
    {
        $this->authorizeRecord($request, $vaccination);
        return $vaccination->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => $this->petIdRule($request),
            'name' => 'required|string|max:255',
            'given_at' => 'nullable|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', now()), 'vaccination date')],
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['user_id'] = $request->user()->id;
        $vaccination = Vaccination::create($data);

        return response()->json($vaccination->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Vaccination $vaccination)
    {
        $this->authorizeRecord($request, $vaccination);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'given_at' => 'sometimes|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', $vaccination->given_at), 'vaccination date')],
            'notes' => 'nullable|string|max:2000',
        ]);
        $vaccination->update($data);

        return $vaccination->load(['pet', 'user']);
    }

    public function destroy(Request $request, Vaccination $vaccination)
    {
        $this->authorizeRecord($request, $vaccination);
        $vaccination->delete();

        return response()->json([], 204);
    }
}
