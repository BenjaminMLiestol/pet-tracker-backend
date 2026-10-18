<?php

namespace App\Http\Controllers;

use App\Models\Feeding;
use App\Services\CoOwnerActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class FeedingController extends RecordController
{
    protected function model(): string
    {
        return Feeding::class;
    }

    protected function supportsClientId(): bool
    {
        return true;
    }

    protected function dateColumn(): string
    {
        return 'fed_at';
    }

    protected function storeRules(Request $request): array
    {
        return ['feed_check' => 'sometimes|boolean', 'fed_at' => 'nullable|date|before_or_equal:+5 minutes'];
    }

    protected function updateRules(Request $request, Model $record): array
    {
        return ['feed_check' => 'sometimes|boolean', 'fed_at' => 'sometimes|date|before_or_equal:+5 minutes'];
    }

    protected function created(Model $record, Request $request): void
    {
        // After the response, so a slow push relay never delays logging.
        $user = $request->user();
        dispatch(fn () => app(CoOwnerActivity::class)->announce('fed', $record->pet, $user, $record->fed_at))->afterResponse();
    }
}
