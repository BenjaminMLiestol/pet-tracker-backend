<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use App\Http\Resources\RecordResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /** Whether entries carry a client-chosen id so a repeated send is recognised (offline queue). */
    protected function supportsClientId(): bool
    {
        return false;
    }

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
        $clientRules = $this->supportsClientId() ? ['client_id' => 'nullable|string|max:64'] : [];
        $data = $request->validate(['pet_id' => $this->petIdRule($request)] + $this->storeRules($request) + $clientRules);
        $data['user_id'] = $request->user()->id;

        // The same entry sent again (a lost reply, an offline queue retry): answer with what
        // was saved the first time, and don't create it twice.
        if (! empty($data['client_id'])) {
            $existing = $this->findByClientId($request, $data['client_id']);
            if ($existing) {
                return (new RecordResource($existing))->response()->setStatusCode(200);
            }
        }
        // An explicit null date means "now": let the column default apply.
        if (array_key_exists($this->dateColumn(), $data) && $data[$this->dateColumn()] === null) {
            unset($data[$this->dateColumn()]);
        }

        try {
            // Re-read so column defaults (an omitted date means now) are filled in.
            $record = $this->model()::create($data)->fresh();
        } catch (UniqueConstraintViolationException $e) {
            // Two sends of the same entry raced: the other one won.
            $existing = ! empty($data['client_id']) ? $this->findByClientId($request, $data['client_id']) : null;
            if (! $existing) {
                throw $e;
            }

            return (new RecordResource($existing))->response()->setStatusCode(200);
        }
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

    private function findByClientId(Request $request, string $clientId): ?Model
    {
        // Including deleted ones: an entry deleted after it was sent must not come back
        // when a stale retry arrives.
        return $this->model()::withTrashed()
            ->where('user_id', $request->user()->id)
            ->where('client_id', $clientId)
            ->first();
    }

    private function find(Request $request, int $id): Model
    {
        $record = $this->model()::findOrFail($id);
        $this->authorizeRecord($request, $record);

        return $record;
    }
}
