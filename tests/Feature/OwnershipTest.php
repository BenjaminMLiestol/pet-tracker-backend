<?php

namespace Tests\Feature;

use App\Models\Feeding;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create(['email' => $email, 'password' => 'secret123', 'first_name' => 'T', 'last_name' => 'U']);
    }

    public function test_user_only_sees_and_touches_own_pets_and_records(): void
    {
        $me = $this->user('me@test.dev');
        $them = $this->user('them@test.dev');
        $mine = Pet::create(['name' => 'Mine']);
        $mine->users()->attach($me);
        $theirs = Pet::create(['name' => 'Theirs']);
        $theirs->users()->attach($them);
        $theirFeeding = Feeding::create(['pet_id' => $theirs->id, 'user_id' => $them->id]);

        Sanctum::actingAs($me);

        $this->getJson('/api/v1/pets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mine');
        $this->getJson("/api/v1/pets/{$theirs->id}")->assertNotFound();
        $this->patchJson("/api/v1/pets/{$theirs->id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/pets/{$theirs->id}")->assertNotFound();

        $this->getJson("/api/v1/feedings?pet_id={$theirs->id}")->assertNotFound();
        $this->getJson('/api/v1/feedings')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/feedings/{$theirFeeding->id}")->assertNotFound();
        $this->deleteJson("/api/v1/feedings/{$theirFeeding->id}")->assertNotFound();
        $this->postJson('/api/v1/feedings', ['pet_id' => $theirs->id])->assertUnprocessable();
        $this->postJson('/api/v1/feedings', ['pet_id' => $mine->id])->assertCreated();
    }

    public function test_users_resource_is_gone_and_self_service_works(): void
    {
        $me = $this->user('me@test.dev');
        $other = $this->user('other@test.dev');
        Sanctum::actingAs($me);

        $this->getJson('/api/v1/users')->assertNotFound();
        $this->deleteJson("/api/v1/users/{$other->id}")->assertNotFound();

        $this->patchJson('/api/v1/auth/me', ['first_name' => 'New'])->assertOk()->assertJsonPath('first_name', 'New');
        $this->patchJson('/api/v1/auth/me', ['email' => 'other@test.dev'])->assertUnprocessable();
        $this->deleteJson('/api/v1/auth/me')->assertNoContent();
        $this->assertSoftDeleted($me);
    }

    public function test_lists_support_per_page_and_since(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Mine']);
        $pet->users()->attach($me);
        foreach (['2026-01-01', '2026-06-01', '2026-10-01'] as $d) {
            Feeding::create(['pet_id' => $pet->id, 'user_id' => $me->id, 'fed_at' => $d]);
        }
        Sanctum::actingAs($me);

        $this->getJson("/api/v1/feedings?pet_id={$pet->id}")->assertJsonCount(3, 'data');
        $this->getJson("/api/v1/feedings?pet_id={$pet->id}&per_page=2")->assertJsonCount(2, 'data')->assertJsonPath('total', 3);
        $this->getJson("/api/v1/feedings?pet_id={$pet->id}&since=2026-05-01")->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/feedings?per_page=9999')->assertUnprocessable();
    }

    public function test_created_pet_belongs_to_creator_and_stays_owned(): void
    {
        $me = $this->user('me@test.dev');
        $other = $this->user('other@test.dev');
        Sanctum::actingAs($me);

        $id = $this->postJson('/api/v1/pets', ['name' => 'Rex'])->assertCreated()->json('id');
        $this->getJson('/api/v1/pets')->assertJsonCount(1, 'data');

        // Nobody can be added by id any more: co-owners join through an invitation code.
        $this->patchJson("/api/v1/pets/$id", ['user_ids' => [$other->id]])->assertOk()->assertJsonCount(1, 'users');
        $this->postJson('/api/v1/pets', ['name' => 'Rex2', 'user_ids' => [$other->id]])
            ->assertCreated()->assertJsonCount(1, 'users');
    }

    public function test_deleting_a_pet_soft_deletes_its_records_and_restore_brings_them_back(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Rex']);
        $pet->users()->attach($me);
        $feeding = Feeding::create(['pet_id' => $pet->id, 'user_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/v1/pets/{$pet->id}")->assertNoContent();
        $this->assertSoftDeleted($feeding);

        $pet->restore();
        $this->assertNotSoftDeleted($feeding);
    }

    public function test_pet_age_is_derived_from_date_of_birth(): void
    {
        Sanctum::actingAs($this->user('me@test.dev'));
        $dob = now()->subYears(3)->subDays(10)->toDateString();

        $this->postJson('/api/v1/pets', ['name' => 'Rex', 'date_of_birth' => $dob])
            ->assertCreated()->assertJsonPath('age', 3);
        $this->postJson('/api/v1/pets', ['name' => 'NoDob'])->assertCreated()->assertJsonPath('age', null);
    }

    public function test_input_limits(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Mine']);
        $pet->users()->attach($me);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/weights', ['pet_id' => $pet->id, 'value' => -3])->assertUnprocessable();
        $this->postJson('/api/v1/weights', ['pet_id' => $pet->id, 'value' => 9000])->assertUnprocessable();
        $this->postJson('/api/v1/weights', ['pet_id' => $pet->id, 'value' => 12.5])->assertCreated();
        $this->postJson('/api/v1/pets', ['name' => str_repeat('a', 256)])->assertUnprocessable();
        $this->postJson('/api/v1/pets', ['name' => 'Future', 'date_of_birth' => now()->addDay()->toDateString()])->assertUnprocessable();
    }

    public function test_explicit_null_date_uses_the_column_default(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Mine']);
        $pet->users()->attach($me);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id, 'fed_at' => null])
            ->assertCreated()->assertJsonPath('feed_check', true)->assertJsonPath('fed_at', fn ($v) => $v !== null);
    }

    public function test_entries_can_be_backdated_and_edited_but_not_set_in_the_future(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Mine']);
        $pet->users()->attach($me);
        Sanctum::actingAs($me);

        $past = now()->subDays(2)->startOfHour();
        $id = $this->postJson('/api/v1/walks', ['pet_id' => $pet->id, 'walked_at' => $past->toIso8601String()])
            ->assertCreated()->json('id');

        $earlier = $past->copy()->subHours(3);
        $this->patchJson("/api/v1/walks/$id", ['walked_at' => $earlier->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('walked_at', fn ($v) => Carbon::parse($v)->equalTo($earlier));

        $future = now()->addHour()->toIso8601String();
        $this->patchJson("/api/v1/walks/$id", ['walked_at' => $future])->assertUnprocessable();
        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id, 'fed_at' => $future])->assertUnprocessable();
        $this->postJson('/api/v1/weights', ['pet_id' => $pet->id, 'value' => 10, 'weighed_at' => $future])->assertUnprocessable();
        $this->postJson('/api/v1/baths', ['pet_id' => $pet->id, 'bathed_at' => $future])->assertUnprocessable();
    }

    public static function resources(): array
    {
        return [
            'feedings' => ['feedings', []],
            'walks' => ['walks', []],
            'baths' => ['baths', []],
            'weights' => ['weights', ['value' => 10]],
            'vet-visits' => ['vet-visits', ['reason' => 'Checkup']],
            'vaccinations' => ['vaccinations', ['name' => 'Rabies']],
            'parasite-treatments' => ['parasite-treatments', ['type' => 'flea']],
        ];
    }

    /** @dataProvider resources */
    public function test_every_record_endpoint_is_ownership_scoped(string $path, array $extra): void
    {
        $me = $this->user('me@test.dev');
        $them = $this->user('them@test.dev');
        $mine = Pet::create(['name' => 'Mine']);
        $mine->users()->attach($me);
        $theirs = Pet::create(['name' => 'Theirs']);
        $theirs->users()->attach($them);

        Sanctum::actingAs($them);
        $theirId = $this->postJson("/api/v1/$path", ['pet_id' => $theirs->id] + $extra)->assertCreated()->json('id');

        Sanctum::actingAs($me);
        $this->postJson("/api/v1/$path", ['pet_id' => $theirs->id] + $extra)->assertUnprocessable();
        $this->postJson("/api/v1/$path", ['pet_id' => $mine->id] + $extra)->assertCreated();
        $this->getJson("/api/v1/$path")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/$path/$theirId")->assertNotFound();
        $this->patchJson("/api/v1/$path/$theirId", [])->assertNotFound();
        $this->deleteJson("/api/v1/$path/$theirId")->assertNotFound();

        Sanctum::actingAs($them);
        $this->getJson("/api/v1/$path/$theirId")->assertOk();
        $this->deleteJson("/api/v1/$path/$theirId")->assertNoContent();
        $this->getJson("/api/v1/$path/$theirId")->assertNotFound();
    }

    public function test_records_do_not_embed_user_or_pet(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Mine']);
        $pet->users()->attach($me);
        Sanctum::actingAs($me);

        $created = $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated()->json();
        $this->assertArrayNotHasKey('user', $created);
        $this->assertArrayNotHasKey('pet', $created);

        $row = $this->getJson("/api/v1/feedings?pet_id={$pet->id}")->json('data.0');
        $this->assertArrayNotHasKey('user', $row);
        $this->assertArrayNotHasKey('pet', $row);
        $this->assertStringNotContainsString('me@test.dev', json_encode($row));
    }

    public function test_pet_json_is_flat_and_hides_co_owner_details(): void
    {
        $me = $this->user('me@test.dev');
        $other = $this->user('other@test.dev');
        Sanctum::actingAs($me);

        $id = $this->postJson('/api/v1/pets', ['name' => 'Rex', 'date_of_birth' => '2021-10-30'])
            ->assertCreated()->json('id');
        Pet::find($id)->users()->attach($other->id);

        $json = $this->getJson("/api/v1/pets/$id")
            ->assertOk()
            ->assertJsonPath('name', 'Rex')
            ->assertJsonPath('date_of_birth', '2021-10-30')
            ->assertJsonCount(2, 'users')
            ->json();

        $this->assertArrayNotHasKey('data', $json);
        $this->assertArrayNotHasKey('deleted_at', $json);
        $this->assertStringNotContainsString('other@test.dev', json_encode($json));
        $this->assertSame(['id', 'first_name', 'last_name'], array_keys($json['users'][0]));
    }

    public function test_pet_timezone_defaults_and_is_validated(): void
    {
        Sanctum::actingAs($this->user('me@test.dev'));

        $id = $this->postJson('/api/v1/pets', ['name' => 'Rex'])->assertCreated()
            ->assertJsonPath('timezone', 'Europe/Oslo')->json('id');
        $this->patchJson("/api/v1/pets/$id", ['timezone' => 'America/New_York'])
            ->assertOk()->assertJsonPath('timezone', 'America/New_York');
        $this->patchJson("/api/v1/pets/$id", ['timezone' => 'Mars/Olympus'])->assertUnprocessable();
    }

    public function test_bath_interval_defaults_to_30_and_is_validated(): void
    {
        $me = $this->user('me@test.dev');
        Sanctum::actingAs($me);

        $id = $this->postJson('/api/v1/pets', ['name' => 'Rex'])->assertCreated()
            ->assertJsonPath('bath_interval_days', 30)->json('id');

        $this->patchJson("/api/v1/pets/$id", ['bath_interval_days' => 14])
            ->assertOk()->assertJsonPath('bath_interval_days', 14);
        $this->getJson("/api/v1/pets/$id")->assertJsonPath('bath_interval_days', 14);

        foreach ([0, -5, 366, 'soon', 1.5] as $bad) {
            $this->patchJson("/api/v1/pets/$id", ['bath_interval_days' => $bad])
                ->assertUnprocessable()->assertJsonValidationErrors('bath_interval_days');
        }
        $this->getJson("/api/v1/pets/$id")->assertJsonPath('bath_interval_days', 14);
    }

    public function test_a_co_owner_can_change_the_shared_bath_interval(): void
    {
        $me = $this->user('me@test.dev');
        $other = $this->user('other@test.dev');
        $pet = Pet::create(['name' => 'Shared']);
        $pet->users()->attach([$me->id, $other->id]);

        Sanctum::actingAs($other);
        $this->patchJson("/api/v1/pets/{$pet->id}", ['bath_interval_days' => 45])->assertOk();

        Sanctum::actingAs($me);
        $this->getJson("/api/v1/pets/{$pet->id}")->assertJsonPath('bath_interval_days', 45);
    }
}
