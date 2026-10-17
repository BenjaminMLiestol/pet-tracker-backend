<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use App\Http\Resources\RecordResource;
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

    /** Runs once a record has been created. Subclasses use it for side effects (pushes). */
    protected function created(Model $record, Request $request): void {}

    public function index(Request $request)
    {
        $query = $this->model()::query()->orderByDesc($this->dateColumn());
        $this->scopeToUserPets($query, $request);

        return $this->paginateRecords($query, $request, $this->dateColumn())
            ->through(fn ($record) => new RecordResource($record));
    }

    public function show(Request $request, int $id)
    {
        return new RecordResource($this->find($request, $id));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['pet_id' => $this->petIdRule($request)] + $this->storeRules($request));
        $data['user_id'] = $request->user()->id;
        // An explicit null date means "now": let the column default apply.
        if (array_key_exists($this->dateColumn(), $data) && $data[$this->dateColumn()] === null) {
            unset($data[$this->dateColumn()]);
        }

        // Re-read so column defaults (an omitted date means now) are filled in.
        $record = $this->model()::create($data)->fresh();
        $this->created($record, $request);

        return (new RecordResource($record))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $record = $this->find($request, $id);
        $record->update($request->validate($this->updateRules($request, $record)));

        return new RecordResource($record);
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
