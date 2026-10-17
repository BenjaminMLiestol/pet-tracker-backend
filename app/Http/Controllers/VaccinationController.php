<?php

namespace App\Http\Controllers;

use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class VaccinationController extends RecordController
{
    protected function model(): string
    {
        return Vaccination::class;
    }

    protected function dateColumn(): string
    {
        return 'given_at';
    }

    protected function storeRules(Request $request): array
    {
        return [
            'name' => 'required|string|max:255',
            'given_at' => 'nullable|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', now()), 'vaccination date')],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    protected function updateRules(Request $request, Model $record): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'given_at' => 'sometimes|date',
            'next_due_at' => ['nullable', 'date', $this->notBeforeDay($request->input('given_at', $record->given_at), 'vaccination date')],
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
