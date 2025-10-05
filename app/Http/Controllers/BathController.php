<?php

// app/Http/Controllers/BathController.php

namespace App\Http\Controllers;

use App\Models\Bath;
use Illuminate\Http\Request;

class BathController extends Controller
{
    public function index(Request $request)
    {
        $query = Bath::with(['pet', 'user'])->orderByDesc('bathed_at');
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->integer('pet_id'));
        }

        return $query->paginate(20);
    }

    public function show(Bath $bath)
    {
        return $bath->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'bathed_at' => 'nullable|date',
        ]);
        $data['user_id'] = $request->user()->id;
        $bath = Bath::create($data);

        return response()->json($bath->load(['pet', 'user']), 201);
    }

    public function update(Request $request, Bath $bath)
    {
        $data = $request->validate([
            'bathed_at' => 'sometimes|date',
        ]);
        $bath->update($data);

        return $bath->load(['pet', 'user']);
    }
}
