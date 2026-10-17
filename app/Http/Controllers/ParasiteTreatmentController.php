<?php

namespace App\Http\Controllers;

use App\Models\ParasiteTreatment;
use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParasiteTreatmentController extends Controller
{
    use AuthorizesPetAccess;

    public function index(Request $request)
    {
        $query = ParasiteTreatment::with(['pet', 'user'])->orderByDesc('given_at');
        $this->scopeToUserPets($query, $request);

        return $this->paginateRecords($query, $request, 'given_at');
    }

    public function show(Request $request, ParasiteTreatment $parasiteTreatment)
    {
        $this->authorizeRecord($request, $parasiteTreatment);
        return $parasiteTreatment->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => $this->petIdRule($request),
            'type' => ['required', Rule::in(['flea', 'tick', 'worm'])],
            'product' => 'nullable|string|max:255',
            'given_at' => 'nullable|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', now()), 'treatment date')],
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['user_id'] = $request->user()->id;
        $parasiteTreatment = ParasiteTreatment::create($data);

        return response()->json($parasiteTreatment->load(['pet', 'user']), 201);
    }

    public function update(Request $request, ParasiteTreatment $parasiteTreatment)
    {
        $this->authorizeRecord($request, $parasiteTreatment);
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['flea', 'tick', 'worm'])],
            'product' => 'nullable|string|max:255',
            'given_at' => 'sometimes|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', $parasiteTreatment->given_at), 'treatment date')],
            'notes' => 'nullable|string|max:2000',
        ]);
        $parasiteTreatment->update($data);

        return $parasiteTreatment->load(['pet', 'user']);
    }

    public function destroy(Request $request, ParasiteTreatment $parasiteTreatment)
    {
        $this->authorizeRecord($request, $parasiteTreatment);
        $parasiteTreatment->delete();

        return response()->json([], 204);
    }
}
