<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function actingPet(): Pet
    {
        Sanctum::actingAs(User::create(['email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B']));

        return Pet::create(['name' => 'Rex']);
    }

    public function test_vet_visit_crud_and_pet_filter(): void
    {
        $pet = $this->actingPet();
        $other = Pet::create(['name' => 'Other']);

        $id = $this->postJson('/api/vet-visits', [
            'pet_id' => $pet->id, 'reason' => 'Checkup',
            'visited_at' => '2026-10-01T10:00:00.000Z', 'next_visit_at' => null, 'notes' => null,
        ])->assertCreated()->json('id');
        $this->postJson('/api/vet-visits', ['pet_id' => $other->id, 'reason' => 'x'])->assertCreated();

        $this->getJson("/api/vet-visits?pet_id={$pet->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/vet-visits/$id")->assertNoContent();
    }

    public function test_vaccination_requires_name(): void
    {
        $pet = $this->actingPet();
        $this->postJson('/api/vaccinations', ['pet_id' => $pet->id])->assertUnprocessable();
        $this->postJson('/api/vaccinations', [
            'pet_id' => $pet->id, 'name' => 'Rabies', 'given_at' => '2026-10-01T10:00:00.000Z',
            'next_due_at' => '2027-10-01T10:00:00.000Z',
        ])->assertCreated()->assertJsonPath('name', 'Rabies');
    }

    public function test_parasite_treatment_validates_type(): void
    {
        $pet = $this->actingPet();
        $this->postJson('/api/parasite-treatments', ['pet_id' => $pet->id, 'type' => 'bogus'])->assertUnprocessable();
        $this->postJson('/api/parasite-treatments', [
            'pet_id' => $pet->id, 'type' => 'flea', 'product' => null, 'given_at' => '2026-10-01T10:00:00.000Z',
        ])->assertCreated()->assertJsonPath('type', 'flea');
    }

    public function test_bath_can_be_deleted(): void
    {
        $pet = $this->actingPet();
        $id = $this->postJson('/api/baths', ['pet_id' => $pet->id])->assertCreated()->json('id');
        $this->deleteJson("/api/baths/$id")->assertNoContent();
        $this->getJson("/api/baths?pet_id={$pet->id}")->assertJsonCount(0, 'data');
    }
}
