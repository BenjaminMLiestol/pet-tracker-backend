<?php

namespace App\Http\Controllers;

use App\Models\Bath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BathController extends RecordController
{
    protected function model(): string
    {
        return Bath::class;
    }

    protected function dateColumn(): string
    {
        return 'bathed_at';
    }

    protected function storeRules(Request $request): array
    {
        return ['bathed_at' => 'nullable|date|before_or_equal:+5 minutes'];
    }

    protected function updateRules(Request $request, Model $record): array
    {
        return ['bathed_at' => 'sometimes|date|before_or_equal:+5 minutes'];
    }
}
