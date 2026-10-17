<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $unauth = $this->call('GET', '/api/pets');
        $this->assertSame(401, $unauth->getStatusCode());
        $this->assertSame('Unauthenticated', $unauth->json('message'));

        Sanctum::actingAs(User::create(['email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B']));
        $missing = $this->call('GET', '/api/feedings/9999');
        $this->assertSame(404, $missing->getStatusCode());
        $this->assertSame('Not found', $missing->json('message'));
        $this->assertSame(404, $this->call('GET', '/api/nope')->getStatusCode());
    }
}
