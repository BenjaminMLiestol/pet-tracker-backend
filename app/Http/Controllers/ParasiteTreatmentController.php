<?php

namespace App\Http\Controllers;

use App\Models\ParasiteTreatment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParasiteTreatmentController extends RecordController
{
    protected function model(): string
    {
        return ParasiteTreatment::class;
    }

    protected function dateColumn(): string
    {
        return 'given_at';
    }

    protected function storeRules(Request $request): array
    {
        return [
            'type' => ['required', Rule::in(['flea', 'tick', 'worm'])],
            'product' => 'nullable|string|max:255',
            'given_at' => 'nullable|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', now()), 'treatment date')],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    protected function updateRules(Request $request, Model $record): array
    {
        return [
            'type' => ['sometimes', Rule::in(['flea', 'tick', 'worm'])],
            'product' => 'nullable|string|max:255',
            'given_at' => 'sometimes|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', $record->given_at), 'treatment date')],
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
