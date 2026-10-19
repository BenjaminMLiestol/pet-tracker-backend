<?php

namespace Tests\Feature;

use App\Models\Feeding;
use App\Models\Pet;
use App\Models\PetInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PetInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create(['email' => $email, 'password' => 'secret123', 'first_name' => 'T', 'last_name' => 'U']);
    }

    /** A dog owned by a new user, who is signed in. */
    private function dogWithOwner(string $email = 'owner@test.dev'): array
    {
        $owner = $this->user($email);
        $pet = Pet::create(['name' => 'Toya']);
        $pet->users()->attach($owner->id);
        Sanctum::actingAs($owner);

        return [$owner, $pet];
    }

    private function invite(Pet $pet): array
    {
        return $this->postJson("/api/v1/pets/{$pet->id}/invitations")->assertCreated()->json();
    }

    public function test_an_owner_can_create_an_invitation_with_a_readable_code(): void
    {
        [, $pet] = $this->dogWithOwner();

        $invitation = $this->invite($pet);

        $this->assertMatchesRegularExpression('/^['.PetInvitation::ALPHABET.']{8}$/', $invitation['code']);
        $this->assertStringNotContainsString('0', $invitation['code']);
        $this->assertTrue(now()->addDays(6)->lt(Carbon::parse($invitation['expires_at'])));
        $this->getJson("/api/v1/pets/{$pet->id}/invitations")->assertOk()->assertJsonCount(1);
    }

    public function test_codes_are_unique(): void
    {
        [, $pet] = $this->dogWithOwner();

        $codes = collect(range(1, PetInvitation::MAX_ACTIVE))->map(fn () => $this->invite($pet)['code']);

        $this->assertCount(PetInvitation::MAX_ACTIVE, $codes->unique());
    }

    public function test_only_owners_can_create_list_or_revoke_invitations(): void
    {
        [, $pet] = $this->dogWithOwner();
        $invitation = $this->invite($pet);

        Sanctum::actingAs($this->user('stranger@test.dev'));
        $this->postJson("/api/v1/pets/{$pet->id}/invitations")->assertNotFound();
        $this->getJson("/api/v1/pets/{$pet->id}/invitations")->assertNotFound();
        $this->deleteJson("/api/v1/pets/{$pet->id}/invitations/{$invitation['id']}")->assertNotFound();
    }

    public function test_a_pet_can_only_have_a_few_open_invitations(): void
    {
        [, $pet] = $this->dogWithOwner();
        foreach (range(1, PetInvitation::MAX_ACTIVE) as $i) {
            $this->invite($pet);
        }

        $this->postJson("/api/v1/pets/{$pet->id}/invitations")
            ->assertUnprocessable()->assertJsonValidationErrors('pet');
    }

    public function test_accepting_a_code_makes_the_user_a_co_owner_who_sees_the_dogs_data(): void
    {
        [$owner, $pet] = $this->dogWithOwner();
        Feeding::create(['pet_id' => $pet->id, 'user_id' => $owner->id]);
        $code = $this->invite($pet)['code'];

        $friend = $this->user('friend@test.dev');
        Sanctum::actingAs($friend);
        $this->getJson("/api/v1/pets/{$pet->id}")->assertNotFound(); // not yet

        $this->postJson('/api/v1/invitations/accept', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('id', $pet->id)
            ->assertJsonCount(2, 'users');

        $this->getJson("/api/v1/pets/{$pet->id}")->assertOk();
        $this->getJson("/api/v1/feedings?pet_id={$pet->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/pets')->assertJsonCount(1, 'data');
    }

    public function test_the_code_is_forgiving_about_case_spaces_and_dashes(): void
    {
        [, $pet] = $this->dogWithOwner();
        $code = $this->invite($pet)['code'];
        $typed = strtolower(substr($code, 0, 4).' - '.substr($code, 4));

        Sanctum::actingAs($this->user('friend@test.dev'));
        $this->postJson('/api/v1/invitations/accept', ['code' => $typed])->assertOk();
    }

    public function test_a_code_works_once(): void
    {
        [, $pet] = $this->dogWithOwner();
        $code = $this->invite($pet)['code'];

        Sanctum::actingAs($this->user('first@test.dev'));
        $this->postJson('/api/v1/invitations/accept', ['code' => $code])->assertOk();

        Sanctum::actingAs($this->user('second@test.dev'));
        $this->postJson('/api/v1/invitations/accept', ['code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->getJson("/api/v1/pets/{$pet->id}")->assertNotFound();
    }

    public function test_expired_revoked_and_unknown_codes_all_fail_the_same_way(): void
    {
        [, $pet] = $this->dogWithOwner();

        $expired = $this->invite($pet);
        PetInvitation::find($expired['id'])->update(['expires_at' => now()->subMinute()]);

        $revoked = $this->invite($pet);
        $this->deleteJson("/api/v1/pets/{$pet->id}/invitations/{$revoked['id']}")->assertNoContent();

        Sanctum::actingAs($this->user('friend@test.dev'));
        $messages = [];
        foreach ([$expired['code'], $revoked['code'], 'ZZZZZZZZ', 'x'] as $code) {
            $response = $this->postJson('/api/v1/invitations/accept', ['code' => $code])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
            $messages[] = $response->json('errors.code.0');
        }

        $this->assertCount(1, array_unique($messages), 'every failure should look identical');
        $this->getJson("/api/v1/pets/{$pet->id}")->assertNotFound();
    }

    public function test_revoked_invitations_disappear_from_the_list(): void
    {
        [, $pet] = $this->dogWithOwner();
        $invitation = $this->invite($pet);

        $this->deleteJson("/api/v1/pets/{$pet->id}/invitations/{$invitation['id']}")->assertNoContent();

        $this->getJson("/api/v1/pets/{$pet->id}/invitations")->assertOk()->assertJsonCount(0);
    }

    public function test_an_owner_cannot_use_their_own_code(): void
    {
        [, $pet] = $this->dogWithOwner();
        $code = $this->invite($pet)['code'];

        $this->postJson('/api/v1/invitations/accept', ['code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        // ...and the code isn't burned by that mistake.
        Sanctum::actingAs($this->user('friend@test.dev'));
        $this->postJson('/api/v1/invitations/accept', ['code' => $code])->assertOk();
    }

    public function test_a_code_for_a_deleted_dog_is_invalid(): void
    {
        [, $pet] = $this->dogWithOwner();
        $code = $this->invite($pet)['code'];
        $pet->delete();

        Sanctum::actingAs($this->user('friend@test.dev'));
        $this->postJson('/api/v1/invitations/accept', ['code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_guessing_codes_is_rate_limited(): void
    {
        Sanctum::actingAs($this->user('guesser@test.dev'));

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/invitations/accept', ['code' => 'ZZZZZZZZ'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/invitations/accept', ['code' => 'ZZZZZZZZ'])->assertStatus(429);
    }

    public function test_an_owner_can_leave_when_someone_else_still_owns_the_dog(): void
    {
        [$owner, $pet] = $this->dogWithOwner();
        $friend = $this->user('friend@test.dev');
        $pet->users()->attach($friend->id);

        $this->deleteJson("/api/v1/pets/{$pet->id}/users/{$owner->id}")->assertNoContent();

        $this->getJson("/api/v1/pets/{$pet->id}")->assertNotFound(); // no longer theirs
        Sanctum::actingAs($friend);
        $this->getJson("/api/v1/pets/{$pet->id}")->assertOk()->assertJsonCount(1, 'users');
    }

    public function test_an_owner_can_remove_a_co_owner(): void
    {
        [, $pet] = $this->dogWithOwner();
        $friend = $this->user('friend@test.dev');
        $pet->users()->attach($friend->id);

        $this->deleteJson("/api/v1/pets/{$pet->id}/users/{$friend->id}")->assertNoContent();

        Sanctum::actingAs($friend);
        $this->getJson("/api/v1/pets/{$pet->id}")->assertNotFound();
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        [$owner, $pet] = $this->dogWithOwner();

        $this->deleteJson("/api/v1/pets/{$pet->id}/users/{$owner->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->getJson("/api/v1/pets/{$pet->id}")->assertOk();
    }

    public function test_strangers_cannot_remove_owners_or_probe_membership(): void
    {
        [$owner, $pet] = $this->dogWithOwner();
        $stranger = $this->user('stranger@test.dev');
        $other = Pet::create(['name' => 'Other']);
        $other->users()->attach($stranger->id);

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/v1/pets/{$pet->id}/users/{$owner->id}")->assertNotFound();
        // Removing someone who isn't an owner of a dog you do own: also a plain 404.
        $this->deleteJson("/api/v1/pets/{$other->id}/users/{$owner->id}")->assertNotFound();
    }
}
