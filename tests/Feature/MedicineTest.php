<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\MedicineDose;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicineTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create(['email' => $email, 'password' => 'secret123', 'first_name' => 'T', 'last_name' => 'U']);
    }

    private function dogFor(User ...$owners): Pet
    {
        $pet = Pet::create(['name' => 'Toya']);
        $pet->users()->attach(array_map(fn ($u) => $u->id, $owners));

        return $pet;
    }

    private function body(Pet $pet, array $over = []): array
    {
        return $over + [
            'pet_id' => $pet->id,
            'name' => 'Apoquel',
            'dose' => '1 tablet',
            'times' => ['20:00', '08:00'],
            'interval_days' => 1,
            'starts_on' => now()->subDays(3)->toDateString(),
        ];
    }

    private function make(Pet $pet, User $by, array $over = []): Medicine
    {
        return Medicine::create($over + [
            'name' => 'Apoquel', 'dose' => null, 'times' => ['08:00', '20:00'], 'interval_days' => 1,
            'starts_on' => now()->subDays(3)->toDateString(), 'ends_on' => null,
            'pet_id' => $pet->id, 'user_id' => $by->id,
        ]);
    }

    public function test_creating_a_medicine_sorts_the_times_and_has_no_end_by_default(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/medicines', $this->body($pet))->assertCreated()
            ->assertJsonPath('times', ['08:00', '20:00'])
            ->assertJsonPath('ends_on', null)
            ->assertJsonPath('interval_days', 1)
            ->assertJsonPath('dose', '1 tablet');
    }

    public function test_a_medicine_added_without_a_start_date_starts_today(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);
        $body = $this->body($pet);
        unset($body['starts_on']);

        $this->postJson('/api/v1/medicines', $body)->assertCreated()->assertJsonPath('starts_on', now()->toDateString());
        // ...and an end date that is already past is refused even without a start date.
        $this->postJson('/api/v1/medicines', $body + ['ends_on' => now()->subDay()->toDateString()])->assertUnprocessable();
    }

    public function test_a_medicine_needs_a_name_valid_times_and_a_sensible_schedule(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);

        $bad = [
            ['name' => ''],
            ['name' => str_repeat('a', 101)],
            ['times' => []],
            ['times' => ['25:00']],
            ['times' => ['8:00']],
            ['times' => ['08:00', '08:00']],
            ['times' => ['01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00']],
            ['interval_days' => 0],
            ['interval_days' => 366],
            ['starts_on' => 'tomorrow-ish'],
            ['ends_on' => now()->subDays(10)->toDateString()], // before the start date
        ];
        foreach ($bad as $over) {
            $this->postJson('/api/v1/medicines', $this->body($pet, $over))->assertUnprocessable();
        }
        $this->assertSame(0, Medicine::count());
    }

    public function test_only_the_dogs_owners_can_see_and_change_its_medicines(): void
    {
        $me = $this->user('me@test.dev');
        $stranger = $this->user('x@test.dev');
        $pet = $this->dogFor($me);
        $medicine = $this->make($pet, $me);

        Sanctum::actingAs($stranger);
        $this->postJson('/api/v1/medicines', $this->body($pet))->assertUnprocessable(); // not their dog
        $this->getJson("/api/v1/medicines?pet_id={$pet->id}")->assertNotFound();
        $this->patchJson("/api/v1/medicines/{$medicine->id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/medicines/{$medicine->id}")->assertNotFound();
        $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => now()->toDateString(), 'slot' => '08:00'])->assertNotFound();
        $this->getJson("/api/v1/medicine-doses?pet_id={$pet->id}")->assertNotFound();
        $this->assertSame('Apoquel', $medicine->fresh()->name);
    }

    public function test_a_co_owner_sees_edits_and_removes_the_same_medicines(): void
    {
        $ben = $this->user('ben@test.dev');
        $kari = $this->user('kari@test.dev');
        $pet = $this->dogFor($ben, $kari);
        $medicine = $this->make($pet, $ben);

        Sanctum::actingAs($kari);
        $this->getJson("/api/v1/medicines?pet_id={$pet->id}")->assertOk()->assertJsonCount(1);
        $this->patchJson("/api/v1/medicines/{$medicine->id}", ['name' => 'Cytopoint', 'times' => ['09:30']])
            ->assertOk()->assertJsonPath('name', 'Cytopoint')->assertJsonPath('times', ['09:30']);
        $this->deleteJson("/api/v1/medicines/{$medicine->id}")->assertNoContent();
        $this->getJson("/api/v1/medicines?pet_id={$pet->id}")->assertJsonCount(0);
    }

    public function test_the_end_date_can_be_set_and_cleared_and_not_set_before_the_start(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        $medicine = $this->make($pet, $me, ['starts_on' => '2026-10-10']);
        Sanctum::actingAs($me);

        $this->patchJson("/api/v1/medicines/{$medicine->id}", ['ends_on' => '2026-10-20'])->assertOk()->assertJsonPath('ends_on', '2026-10-20');
        $this->patchJson("/api/v1/medicines/{$medicine->id}", ['ends_on' => '2026-10-01'])->assertUnprocessable();
        $this->patchJson("/api/v1/medicines/{$medicine->id}", ['ends_on' => null])->assertOk()->assertJsonPath('ends_on', null);
    }

    public function test_the_most_medicines_per_dog_is_limited(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);
        for ($i = 0; $i < 20; $i++) {
            $this->make($pet, $me, ['name' => "M$i"]);
        }

        $this->postJson('/api/v1/medicines', $this->body($pet))->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public static function schedules(): array
    {
        // [interval, starts_on, ends_on, day to ask about, due?]
        return [
            'every day' => [1, '2026-10-10', null, '2026-10-17', true],
            'before the start' => [1, '2026-10-10', null, '2026-10-09', false],
            'every 3rd day, on the start' => [3, '2026-10-10', null, '2026-10-10', true],
            'every 3rd day, a day later' => [3, '2026-10-10', null, '2026-10-11', false],
            'every 3rd day, two days later' => [3, '2026-10-10', null, '2026-10-12', false],
            'every 3rd day, three days later' => [3, '2026-10-10', null, '2026-10-13', true],
            'every 30 days across a month end' => [30, '2026-10-10', null, '2026-11-09', true],
            'every 30 days, a day off' => [30, '2026-10-10', null, '2026-11-08', false],
            'the last day of a course' => [1, '2026-10-10', '2026-10-14', '2026-10-14', true],
            'the day after a course' => [1, '2026-10-10', '2026-10-14', '2026-10-15', false],
            'forever, a long time on' => [7, '2026-01-05', null, '2027-01-04', true],
        ];
    }

    /** @dataProvider schedules */
    #[DataProvider('schedules')]
    public function test_which_days_a_medicine_is_due(int $interval, string $start, ?string $end, string $day, bool $due): void
    {
        $me = $this->user('me@test.dev');
        $medicine = $this->make($this->dogFor($me), $me, ['interval_days' => $interval, 'starts_on' => $start, 'ends_on' => $end]);

        $this->assertSame($due, $medicine->isDueOn($day));
    }

    public function test_ticking_a_dose_works_once_and_a_repeat_returns_the_same_dose(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        $medicine = $this->make($pet, $me);
        Sanctum::actingAs($me);
        $today = now()->toDateString();

        $first = $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => $today, 'slot' => '08:00'])
            ->assertCreated()->assertJsonPath('for', $today)->assertJsonPath('slot', '08:00')->json('id');
        $second = $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => $today, 'slot' => '08:00'])
            ->assertOk()->json('id');
        $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => $today, 'slot' => '20:00'])->assertCreated();

        $this->assertSame($first, $second);
        $this->assertSame(2, MedicineDose::count());
    }

    public function test_a_co_owner_ticking_counts_for_everyone_and_shows_who_did_it(): void
    {
        $ben = $this->user('ben@test.dev');
        $kari = $this->user('kari@test.dev');
        $pet = $this->dogFor($ben, $kari);
        $medicine = $this->make($pet, $ben);
        $today = now()->toDateString();

        Sanctum::actingAs($kari);
        $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => $today, 'slot' => '08:00'])->assertCreated();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($ben);
        $this->getJson("/api/v1/medicine-doses?pet_id={$pet->id}")->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.user_id', $kari->id);
        // Ben ticking the same dose afterwards does not make a second one or change who gave it.
        $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => $today, 'slot' => '08:00'])->assertOk()->assertJsonPath('user_id', $kari->id);
        $this->assertSame(1, MedicineDose::count());
    }

    public function test_a_dose_must_be_for_a_real_slot_on_a_due_day_close_to_today(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        $medicine = $this->make($pet, $me, ['interval_days' => 2, 'starts_on' => now()->subDays(2)->toDateString()]);
        Sanctum::actingAs($me);
        $url = "/api/v1/medicines/{$medicine->id}/doses";

        $this->putJson($url, ['for' => now()->toDateString(), 'slot' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors('slot');
        $this->putJson($url, ['for' => now()->subDay()->toDateString(), 'slot' => '08:00'])->assertUnprocessable()->assertJsonValidationErrors('for'); // an off day
        $this->putJson($url, ['for' => now()->subDays(2)->toDateString(), 'slot' => '08:00'])->assertCreated(); // a due day, a little back
        $this->putJson($url, ['for' => now()->addDays(4)->toDateString(), 'slot' => '08:00'])->assertUnprocessable(); // too far ahead
        $this->putJson($url, ['for' => 'soon', 'slot' => '08:00'])->assertUnprocessable();
        $this->putJson($url, ['slot' => '08:00'])->assertUnprocessable();

        $old = $this->make($pet, $me, ['starts_on' => now()->subDays(30)->toDateString()]);
        $this->putJson("/api/v1/medicines/{$old->id}/doses", ['for' => now()->subDays(20)->toDateString(), 'slot' => '08:00'])->assertUnprocessable(); // too far back
    }

    public function test_a_tick_can_be_taken_back_and_taking_back_nothing_is_fine(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        $medicine = $this->make($pet, $me);
        Sanctum::actingAs($me);
        $today = now()->toDateString();
        $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => $today, 'slot' => '08:00'])->assertCreated();

        $this->deleteJson("/api/v1/medicines/{$medicine->id}/doses?for=$today&slot=08:00")->assertNoContent();
        $this->deleteJson("/api/v1/medicines/{$medicine->id}/doses?for=$today&slot=08:00")->assertNoContent();
        $this->assertSame(0, MedicineDose::count());
        $this->deleteJson("/api/v1/medicines/{$medicine->id}/doses?for=nope&slot=08:00")->assertUnprocessable();
    }

    public function test_doses_can_be_listed_since_a_day_and_only_for_that_dog(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        $other = $this->dogFor($me);
        $mine = $this->make($pet, $me);
        $theirs = $this->make($other, $me);
        foreach ([[$mine, 5], [$mine, 1], [$theirs, 1]] as [$m, $daysAgo]) {
            MedicineDose::create(['medicine_id' => $m->id, 'user_id' => $me->id, 'given_for' => now()->subDays($daysAgo)->toDateString(), 'slot' => '08:00']);
        }
        Sanctum::actingAs($me);

        $this->getJson("/api/v1/medicine-doses?pet_id={$pet->id}")->assertJsonCount(2);
        $this->getJson("/api/v1/medicine-doses?pet_id={$pet->id}&since=".now()->subDays(2)->toDateString())->assertJsonCount(1);
        $this->getJson('/api/v1/medicine-doses')->assertUnprocessable();
    }

    public function test_a_removed_medicine_stops_showing_and_deleting_the_dog_removes_its_medicines(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        $medicine = $this->make($pet, $me);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/v1/medicines/{$medicine->id}")->assertNoContent();
        $this->putJson("/api/v1/medicines/{$medicine->id}/doses", ['for' => now()->toDateString(), 'slot' => '08:00'])->assertNotFound();

        $kept = $this->make($pet, $me, ['name' => 'Other']);
        $this->deleteJson("/api/v1/pets/{$pet->id}")->assertNoContent();
        $this->assertNull(Medicine::find($kept->id));
        $this->assertNotNull(Medicine::withTrashed()->find($kept->id));
    }
}
