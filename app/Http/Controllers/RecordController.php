<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared CRUD for per-pet records (feedings, walks, care records, ...).
 * Subclasses declare the model, its date column and the validation rules.
 */
abstract class RecordController extends Controller
{
    use AuthorizesPetAccess;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Timestamp column used for ordering and the ?since filter. */
    abstract protected function dateColumn(): string;

    /** Validation rules for POST, excluding pet_id. */
    abstract protected function storeRules(Request $request): array;

    /** Validation rules for PATCH/PUT. */
    abstract protected function updateRules(Request $request, Model $record): array;

    public function index(Request $request)
    {
        $query = $this->model()::with(['pet', 'user'])->orderByDesc($this->dateColumn());
        $this->scopeToUserPets($query, $request);

        return $this->paginateRecords($query, $request, $this->dateColumn());
    }

    public function show(Request $request, int $id)
    {
        return $this->find($request, $id)->load(['pet', 'user']);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['pet_id' => $this->petIdRule($request)] + $this->storeRules($request));
        $data['user_id'] = $request->user()->id;
        // An explicit null date means "now": let the column default apply.
        if (array_key_exists($this->dateColumn(), $data) && $data[$this->dateColumn()] === null) {
            unset($data[$this->dateColumn()]);
        }

        $record = $this->model()::create($data);

        return response()->json($record->fresh()->load(['pet', 'user']), 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $this->find($request, $id);
        $record->update($request->validate($this->updateRules($request, $record)));

        return $record->load(['pet', 'user']);
    }

    public function destroy(Request $request, int $id)
    {
        $this->find($request, $id)->delete();

        return response()->json([], 204);
    }

    private function find(Request $request, int $id): Model
    {
        $record = $this->model()::findOrFail($id);
        $this->authorizeRecord($request, $record);

        return $record;
    }
}
