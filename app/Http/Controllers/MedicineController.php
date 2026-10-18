<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesPetAccess;
use App\Http\Resources\MedicineDoseResource;
use App\Http\Resources\MedicineResource;
use App\Models\Medicine;
use App\Models\MedicineDose;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Medicines on a schedule, and the doses given. Every owner of the dog sees and changes the
 * same ones: a dose ticked off by anyone counts for everyone.
 */
class MedicineController extends Controller
{
    use AuthorizesPetAccess;

    public const MAX_PER_PET = 20;

    // A dose can be ticked for today and a little back (forgotten yesterday), not for any day.
    private const DAYS_BACK = 7;

    public function index(Request $request)
    {
        $query = Medicine::query()->orderBy('name');
        $this->scopeToUserPets($query, $request);

        return MedicineResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['pet_id' => $this->petIdRule($request)] + $this->rules(false));

        if (Medicine::where('pet_id', $data['pet_id'])->count() >= self::MAX_PER_PET) {
            throw ValidationException::withMessages(['name' => 'This dog already has the most medicines allowed.']);
        }

        $data['starts_on'] ??= now()->toDateString();
        if (isset($data['ends_on']) && $data['ends_on'] < $data['starts_on']) {
            throw ValidationException::withMessages(['ends_on' => 'The end date can not be before the start date.']);
        }

        $medicine = Medicine::create($this->prepare($data) + ['user_id' => $request->user()->id]);

        return (new MedicineResource($medicine))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $medicine = $this->find($request, $id);
        $data = $request->validate($this->rules(true));

        $startsOn = $data['starts_on'] ?? $medicine->starts_on->toDateString();
        $endsOn = array_key_exists('ends_on', $data) ? $data['ends_on'] : $medicine->ends_on?->toDateString();
        if ($endsOn !== null && $endsOn < $startsOn) {
            throw ValidationException::withMessages(['ends_on' => 'The end date can not be before the start date.']);
        }

        $medicine->update($this->prepare($data));

        return new MedicineResource($medicine->fresh());
    }

    public function destroy(Request $request, int $id)
    {
        $this->find($request, $id)->delete();

        return response()->json([], 204);
    }

    /** The doses given for a dog's medicines since a day (default: the last two weeks). */
    public function doses(Request $request)
    {
        $params = $request->validate(['pet_id' => 'required|integer', 'since' => 'sometimes|date']);
        abort_unless(in_array((int) $params['pet_id'], $this->ownedPetIds($request), true), 404);

        $since = $params['since'] ?? now()->subDays(14)->toDateString();
        $doses = MedicineDose::query()
            ->whereHas('medicine', fn ($q) => $q->where('pet_id', $params['pet_id']))
            ->where('given_for', '>=', $since)
            ->orderBy('given_for')->orderBy('slot')
            ->get();

        return MedicineDoseResource::collection($doses);
    }

    /** Tick a dose off. Ticking the same dose again answers with the one already there. */
    public function give(Request $request, int $id)
    {
        $medicine = $this->find($request, $id);
        [$for, $slot] = $this->doseKey($request, $medicine);

        $existing = MedicineDose::where(['medicine_id' => $medicine->id, 'given_for' => $for, 'slot' => $slot])->first();
        if ($existing) {
            return new MedicineDoseResource($existing);
        }

        try {
            $dose = MedicineDose::create([
                'medicine_id' => $medicine->id,
                'user_id' => $request->user()->id,
                'given_for' => $for,
                'slot' => $slot,
                'given_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two owners ticked it at the same moment: the other one won.
            return new MedicineDoseResource(
                MedicineDose::where(['medicine_id' => $medicine->id, 'given_for' => $for, 'slot' => $slot])->firstOrFail(),
            );
        }

        return (new MedicineDoseResource($dose))->response()->setStatusCode(201);
    }

    /** Take a tick back (it was a mistake). Nothing there is fine too. */
    public function undo(Request $request, int $id)
    {
        $medicine = $this->find($request, $id);
        $data = $request->validate(['for' => 'required|date_format:Y-m-d', 'slot' => 'required|date_format:H:i']);
        MedicineDose::where(['medicine_id' => $medicine->id, 'given_for' => $data['for'], 'slot' => $data['slot']])->delete();

        return response()->json([], 204);
    }

    // ---------------------------------------------------------------

    private function rules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:100'],
            'dose' => ['nullable', 'string', 'max:100'],
            'times' => [$req, 'array', 'min:1', 'max:6'],
            'times.*' => ['required', 'date_format:H:i', 'distinct'],
            'interval_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            // Left out when adding = starting today.
            'starts_on' => ['sometimes', 'date_format:Y-m-d'],
            // null (or left out) = runs until it is removed.
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    private function prepare(array $data): array
    {
        if (isset($data['times'])) {
            $times = array_values(array_unique($data['times']));
            sort($times);
            $data['times'] = $times;
        }
        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }
        if (array_key_exists('dose', $data)) {
            $data['dose'] = $data['dose'] === null || trim($data['dose']) === '' ? null : trim($data['dose']);
        }

        return $data;
    }

    /** Checks a dose is for a real slot on a day the medicine is due, and not too far back or ahead. */
    private function doseKey(Request $request, Medicine $medicine): array
    {
        $data = $request->validate(['for' => 'required|date_format:Y-m-d', 'slot' => 'required|date_format:H:i']);

        if (! in_array($data['slot'], $medicine->times, true)) {
            throw ValidationException::withMessages(['slot' => 'That is not one of the medicine\'s times.']);
        }
        if (! $medicine->isDueOn($data['for'])) {
            throw ValidationException::withMessages(['for' => 'The medicine is not due on that day.']);
        }
        // One day of slack ahead, for someone in a timezone ahead of the server's.
        if ($data['for'] > now()->addDay()->toDateString() || $data['for'] < now()->subDays(self::DAYS_BACK)->toDateString()) {
            throw ValidationException::withMessages(['for' => 'That day is too far from today.']);
        }

        return [$data['for'], $data['slot']];
    }

    private function find(Request $request, int $id): Medicine
    {
        $medicine = Medicine::findOrFail($id);
        abort_unless(in_array($medicine->pet_id, $this->ownedPetIds($request), true), 404);

        return $medicine;
    }
}
