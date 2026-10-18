<?php

namespace Tests\Feature;

use App\Models\Feeding;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IdempotentEntriesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create(['email' => $email, 'password' => 'secret123', 'first_name' => 'T', 'last_name' => 'U']);
    }

    private function dogFor(User $user, string $name = 'Mine'): Pet
    {
        $pet = Pet::create(['name' => $name]);
        $pet->users()->attach($user);

        return $pet;
    }

    public static function entryKinds(): array
    {
        return [
            'feedings' => ['feedings', []],
            'walks' => ['walks', []],
            'baths' => ['baths', []],
            'weights' => ['weights', ['value' => 12.5]],
        ];
    }

    #[DataProvider('entryKinds')]
    public function test_sending_the_same_entry_twice_saves_it_once(string $path, array $extra): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);
        $body = ['pet_id' => $pet->id, 'client_id' => 'abc-123'] + $extra;

        $first = $this->postJson("/api/v1/$path", $body)->assertCreated();
        $second = $this->postJson("/api/v1/$path", $body)->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, $this->app->make('App\\Models\\'.ucfirst(rtrim($path, 's')))::count());
    }

    public function test_entries_without_a_client_id_still_work_and_are_not_merged(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated();
        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated();

        $this->assertSame(2, Feeding::count());
    }

    public function test_the_same_client_id_from_two_people_is_two_entries(): void
    {
        $ben = $this->user('ben@test.dev');
        $kari = $this->user('kari@test.dev');
        $pet = $this->dogFor($ben);
        $pet->users()->attach($kari);

        Sanctum::actingAs($ben);
        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id, 'client_id' => 'same'])->assertCreated();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($kari);
        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id, 'client_id' => 'same'])->assertCreated();

        $this->assertSame(2, Feeding::count());
    }

    public function test_a_stale_retry_does_not_bring_back_a_deleted_entry(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);
        $body = ['pet_id' => $pet->id, 'client_id' => 'gone'];

        $id = $this->postJson('/api/v1/feedings', $body)->assertCreated()->json('id');
        $this->deleteJson("/api/v1/feedings/$id")->assertNoContent();
        $this->postJson('/api/v1/feedings', $body)->assertOk();

        $this->assertSame(0, Feeding::count());
        $this->assertSame(1, Feeding::withTrashed()->count());
    }

    public function test_the_client_id_must_be_a_short_string(): void
    {
        $me = $this->user('me@test.dev');
        $pet = $this->dogFor($me);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id, 'client_id' => str_repeat('x', 65)])->assertUnprocessable();
    }
}
