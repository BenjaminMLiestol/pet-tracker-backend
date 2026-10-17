<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Restricts activity records to pets the authenticated user owns.
 * Foreign records answer 404 so their existence isn't revealed.
 */
trait AuthorizesPetAccess
{
    protected function ownedPetIds(Request $request): array
    {
        return Pet::ownedBy($request->user())->pluck('id')->all();
    }

    protected function petIdRule(Request $request): array
    {
        return ['required', 'integer', Rule::in($this->ownedPetIds($request))];
    }

    protected function scopeToUserPets(Builder $query, Request $request): void
    {
        $ids = $this->ownedPetIds($request);

        if ($request->has('pet_id')) {
            $petId = $request->integer('pet_id');
            abort_unless(in_array($petId, $ids, true), 404);
            $query->where('pet_id', $petId);

            return;
        }

        $query->whereIn('pet_id', $ids);
    }

    protected function authorizeRecord(Request $request, Model $record): void
    {
        abort_unless(in_array($record->pet_id, $this->ownedPetIds($request), true), 404);
    }

    /**
     * Paginate a list: ?per_page=1..500 (default 100) and ?since=<date> filter on $dateColumn.
     */
    protected function paginateRecords(Builder $query, Request $request, string $dateColumn): LengthAwarePaginator
    {
        $params = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:500',
            'since' => 'sometimes|date',
        ]);

        if (isset($params['since'])) {
            $query->where($dateColumn, '>=', $params['since']);
        }

        return $query->paginate($params['per_page'] ?? 100);
    }
}
