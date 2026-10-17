<?php

namespace App\Http\Controllers;

use App\Models\Walk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class WalkController extends RecordController
{
    protected function model(): string
    {
        return Walk::class;
    }

    protected function dateColumn(): string
    {
        return 'walked_at';
    }

    protected function storeRules(Request $request): array
    {
        return ['walk_check' => 'sometimes|boolean', 'walked_at' => 'nullable|date|before_or_equal:+5 minutes'];
    }

    protected function updateRules(Request $request, Model $record): array
    {
        return ['walk_check' => 'sometimes|boolean', 'walked_at' => 'sometimes|date|before_or_equal:+5 minutes'];
    }
}
