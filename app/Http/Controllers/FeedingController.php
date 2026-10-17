<?php

// app/Http/Controllers/FeedingController.php

namespace App\Http\Controllers;

use App\Models\Feeding;
use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use Illuminate\Http\Request;

class FeedingController extends Controller
{
    use AuthorizesPetAccess;

    public function index(Request $request)
    {
        $query = Feeding::with(['pet', 'user'])->orderByDesc('fed_at');
        $this->scopeToUserPets($query, $request);

        return $this->paginateRecords($query, $request, 'fed_at');
    }

    public function show(Request $request, Feeding $feeding)
    {
        $this->authorizeRecord($request, $feeding);
        return $feeding->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => $this->petIdRule($request),
            'feed_check' => 'sometimes|boolean',
            'fed_at' => 'nullable|date',
        ]);
        $data['user_id'] = $request->user()->id;
        $feeding = Feeding::create($data + ['feed_check' => $data['feed_check'] ?? true]);

        return response()->json($feeding->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Feeding $feeding)
    {
        $this->authorizeRecord($request, $feeding);
        $data = $request->validate([
            'feed_check' => 'sometimes|boolean',
            'fed_at' => 'sometimes|date',
        ]);
        $feeding->update($data);

        return $feeding->load(['pet', 'user']);
    }

    public function destroy(Request $request, Feeding $feeding)
    {
        $this->authorizeRecord($request, $feeding);
        $feeding->delete();

        return response()->json([], 204);
    }
}
