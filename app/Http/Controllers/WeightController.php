<?php

// app/Http/Controllers/WeightController.php

namespace App\Http\Controllers;

use App\Models\Weight;
use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use Illuminate\Http\Request;

class WeightController extends Controller
{
    use AuthorizesPetAccess;

    public function index(Request $request)
    {
        $query = Weight::with(['pet', 'user'])->orderByDesc('weighed_at');
        $this->scopeToUserPets($query, $request);

        return $query->paginate(20);
    }

    public function show(Request $request, Weight $weight)
    {
        $this->authorizeRecord($request, $weight);
        return $weight->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => $this->petIdRule($request),
            'value' => 'required|numeric',
            'weighed_at' => 'nullable|date',
        ]);
        $data['user_id'] = $request->user()->id;
        $weight = Weight::create($data);

        return response()->json($weight->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Weight $weight)
    {
        $this->authorizeRecord($request, $weight);
        $data = $request->validate([
            'value' => 'sometimes|numeric',
            'weighed_at' => 'sometimes|date',
        ]);
        $weight->update($data);

        return $weight->load(['pet', 'user']);
    }

    public function destroy(Request $request, Weight $weight)
    {
        $this->authorizeRecord($request, $weight);
        $weight->delete();

        return response()->json([], 204);
    }
}
