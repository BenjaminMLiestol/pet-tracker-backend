<?php

namespace App\Http\Controllers;

use App\Models\Feeding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class FeedingController extends RecordController
{
    protected function model(): string
    {
        return Feeding::class;
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
}
