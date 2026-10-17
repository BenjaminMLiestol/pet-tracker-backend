<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_nothing_without_configured_users(): void
    {
        config(['seeding.users' => []]);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_seeds_configured_users_and_keeps_existing_passwords(): void
    {
        config(['seeding.users' => [
            ['email' => 'one@test.dev', 'password' => 'chosen-pass-1', 'first_name' => 'One', 'last_name' => 'U', 'date_of_birth' => null],
            ['email' => 'two@test.dev', 'password' => null, 'first_name' => 'Two', 'last_name' => 'U', 'date_of_birth' => '1990-01-02'],
        ]]);
        $this->seed(DatabaseSeeder::class);

        $one = User::where('email', 'one@test.dev')->first();
        $two = User::where('email', 'two@test.dev')->first();
        $this->assertTrue(Hash::check('chosen-pass-1', $one->password));
        $this->assertNotEmpty($two->password);
        $this->assertCount(2, Pet::where('name', 'Toya')->first()->users);

        // Re-seeding without a configured password must not reset it.
        $before = $two->password;
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, $two->fresh()->password);
    }
}
