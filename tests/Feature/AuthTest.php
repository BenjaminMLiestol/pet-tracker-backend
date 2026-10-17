<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'wrong']);
        }

        $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_bad_credentials_return_401_and_good_ones_a_token(): void
    {
        User::create(['email' => 'a@b.test', 'password' => bcrypt('secret123'), 'first_name' => 'A', 'last_name' => 'B']);

        $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials');
        $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }
}
