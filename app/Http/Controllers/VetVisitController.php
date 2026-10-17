<?php

namespace App\Http\Controllers;

use App\Models\VetVisit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class VetVisitController extends RecordController
{
    protected function model(): string
    {
        return VetVisit::class;
    }

    protected function dateColumn(): string
    {
        return 'visited_at';
    }

    protected function storeRules(Request $request): array
    {
        return [
            'reason' => 'required|string|max:255',
            'visited_at' => 'nullable|date',
            'next_visit_at' => ['nullable', 'date', $this->notBeforeDay($request->input('visited_at', now()), 'visit date')],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    protected function updateRules(Request $request, Model $record): array
    {
        return [
            'reason' => 'sometimes|string|max:255',
            'visited_at' => 'sometimes|date',
            'next_visit_at' => ['nullable', 'date', $this->notBeforeDay($request->input('visited_at', $record->visited_at), 'visit date')],
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
