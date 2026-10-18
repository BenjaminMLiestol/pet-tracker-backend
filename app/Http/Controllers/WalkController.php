<?php

namespace App\Http\Controllers;

use App\Models\Walk;
use App\Services\CoOwnerActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class WalkController extends RecordController
{
    protected function model(): string
    {
        return Walk::class;
    }

    protected function supportsClientId(): bool
    {
        return true;
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

    protected function created(Model $record, Request $request): void
    {
        // After the response, so a slow push relay never delays logging.
        $user = $request->user();
        dispatch(fn () => app(CoOwnerActivity::class)->announce('walked', $record->pet, $user, $record->walked_at))->afterResponse();
    }
}
