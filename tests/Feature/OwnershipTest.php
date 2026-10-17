<?php

namespace Tests\Feature;

use App\Models\Feeding;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->getJson('/api/pets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mine');
        $this->getJson("/api/pets/{$theirs->id}")->assertNotFound();
        $this->patchJson("/api/pets/{$theirs->id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/pets/{$theirs->id}")->assertNotFound();

        $this->getJson("/api/feedings?pet_id={$theirs->id}")->assertNotFound();
        $this->getJson('/api/feedings')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/feedings/{$theirFeeding->id}")->assertNotFound();
        $this->deleteJson("/api/feedings/{$theirFeeding->id}")->assertNotFound();
        $this->postJson('/api/feedings', ['pet_id' => $theirs->id])->assertUnprocessable();
        $this->postJson('/api/feedings', ['pet_id' => $mine->id])->assertCreated();
    }

    public function test_users_resource_is_gone_and_self_service_works(): void
    {
        $me = $this->user('me@test.dev');
        $other = $this->user('other@test.dev');
        Sanctum::actingAs($me);

        $this->getJson('/api/users')->assertNotFound();
        $this->deleteJson("/api/users/{$other->id}")->assertNotFound();

        $this->patchJson('/api/auth/me', ['first_name' => 'New'])->assertOk()->assertJsonPath('first_name', 'New');
        $this->patchJson('/api/auth/me', ['email' => 'other@test.dev'])->assertUnprocessable();
        $this->deleteJson('/api/auth/me')->assertNoContent();
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

        $this->getJson("/api/feedings?pet_id={$pet->id}")->assertJsonCount(3, 'data');
        $this->getJson("/api/feedings?pet_id={$pet->id}&per_page=2")->assertJsonCount(2, 'data')->assertJsonPath('total', 3);
        $this->getJson("/api/feedings?pet_id={$pet->id}&since=2026-05-01")->assertJsonCount(2, 'data');
        $this->getJson("/api/feedings?per_page=9999")->assertUnprocessable();
    }

    public function test_created_pet_belongs_to_creator_and_stays_owned(): void
    {
        $me = $this->user('me@test.dev');
        $other = $this->user('other@test.dev');
        Sanctum::actingAs($me);

        $id = $this->postJson('/api/pets', ['name' => 'Rex'])->assertCreated()->json('id');
        $this->getJson('/api/pets')->assertJsonCount(1, 'data');

        $this->patchJson("/api/pets/$id", ['user_ids' => [$other->id]])->assertOk()->assertJsonCount(2, 'users');
        $this->patchJson("/api/pets/$id", ['user_ids' => []])->assertOk()->assertJsonCount(1, 'users');
    }

    public function test_deleting_a_pet_soft_deletes_its_records_and_restore_brings_them_back(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Rex']);
        $pet->users()->attach($me);
        $feeding = Feeding::create(['pet_id' => $pet->id, 'user_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/pets/{$pet->id}")->assertNoContent();
        $this->assertSoftDeleted($feeding);

        $pet->restore();
        $this->assertNotSoftDeleted($feeding);
    }

    public function test_pet_age_is_derived_from_date_of_birth(): void
    {
        Sanctum::actingAs($this->user('me@test.dev'));
        $dob = now()->subYears(3)->subDays(10)->toDateString();

        $this->postJson('/api/pets', ['name' => 'Rex', 'date_of_birth' => $dob])
            ->assertCreated()->assertJsonPath('age', 3);
        $this->postJson('/api/pets', ['name' => 'NoDob'])->assertCreated()->assertJsonPath('age', null);
    }

    public function test_input_limits(): void
    {
        $me = $this->user('me@test.dev');
        $pet = Pet::create(['name' => 'Mine']);
        $pet->users()->attach($me);
        Sanctum::actingAs($me);

        $this->postJson('/api/weights', ['pet_id' => $pet->id, 'value' => -3])->assertUnprocessable();
        $this->postJson('/api/weights', ['pet_id' => $pet->id, 'value' => 9000])->assertUnprocessable();
        $this->postJson('/api/weights', ['pet_id' => $pet->id, 'value' => 12.5])->assertCreated();
        $this->postJson('/api/pets', ['name' => str_repeat('a', 256)])->assertUnprocessable();
        $this->postJson('/api/pets', ['name' => 'Future', 'date_of_birth' => now()->addDay()->toDateString()])->assertUnprocessable();
    }
}
