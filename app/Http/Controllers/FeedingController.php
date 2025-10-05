<?php

// app/Http/Controllers/FeedingController.php

namespace App\Http\Controllers;

use App\Models\Feeding;
use Illuminate\Http\Request;

class FeedingController extends Controller
{
    public function index(Request $request)
    {
        $query = Feeding::with(['pet', 'user'])->orderByDesc('fed_at');
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->integer('pet_id'));
        }

        return $query->paginate(20);
    }

    public function show(Feeding $feeding)
    {
        return $feeding->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'feed_check' => 'sometimes|boolean',
            'fed_at' => 'nullable|date',
        ]);
        $data['user_id'] = $request->user()->id;
        $feeding = Feeding::create($data + ['feed_check' => $data['feed_check'] ?? true]);

        return response()->json($feeding->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Feeding $feeding)
    {
        $data = $request->validate([
            'feed_check' => 'sometimes|boolean',
            'fed_at' => 'sometimes|date',
        ]);
        $feeding->update($data);

        return $feeding->load(['pet', 'user']);
    }

    public function destroy(Feeding $feeding)
    {
        $feeding->delete();

        return response()->json([], 204);
    }
}
