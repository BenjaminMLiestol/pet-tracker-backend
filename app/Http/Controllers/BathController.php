<?php

// app/Http/Controllers/BathController.php

namespace App\Http\Controllers;

use App\Models\Bath;
use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use Illuminate\Http\Request;

class BathController extends Controller
{
    use AuthorizesPetAccess;

    public function index(Request $request)
    {
        $query = Bath::with(['pet', 'user'])->orderByDesc('bathed_at');
        $this->scopeToUserPets($query, $request);

        return $this->paginateRecords($query, $request, 'bathed_at');
    }

    public function show(Request $request, Bath $bath)
    {
        $this->authorizeRecord($request, $bath);
        return $bath->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => $this->petIdRule($request),
            'bathed_at' => 'nullable|date',
        ]);
        $data['user_id'] = $request->user()->id;
        $bath = Bath::create($data);

        return response()->json($bath->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Bath $bath)
    {
        $this->authorizeRecord($request, $bath);
        $data = $request->validate([
            'bathed_at' => 'sometimes|date',
        ]);
        $bath->update($data);

        return $bath->load(['pet', 'user']);
    }

    public function destroy(Request $request, Bath $bath)
    {
        $this->authorizeRecord($request, $bath);
        $bath->delete();

        return response()->json([], 204);
    }
}
