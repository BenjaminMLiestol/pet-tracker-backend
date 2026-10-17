<?php

namespace App\Http\Controllers;

use App\Models\Weight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class WeightController extends RecordController
{
    protected function model(): string
    {
        return Weight::class;
    }

    protected function dateColumn(): string
    {
        return 'weighed_at';
    }

    protected function storeRules(Request $request): array
    {
        return ['value' => 'required|numeric|min:0.01|max:500', 'weighed_at' => 'nullable|date|before_or_equal:+5 minutes'];
    }

    protected function updateRules(Request $request, Model $record): array
    {
        return ['value' => 'sometimes|numeric|min:0.01|max:500', 'weighed_at' => 'sometimes|date|before_or_equal:+5 minutes'];
    }
}
