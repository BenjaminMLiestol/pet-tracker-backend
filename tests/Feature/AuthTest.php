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
            $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test', 'password' => 'wrong']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_bad_credentials_return_401_and_good_ones_a_token(): void
    {
        User::create(['email' => 'a@b.test', 'password' => bcrypt('secret123'), 'first_name' => 'A', 'last_name' => 'B']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test', 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials');
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $unauth = $this->call('GET', '/api/v1/pets');
        $this->assertSame(401, $unauth->getStatusCode());
        $this->assertSame('Unauthenticated', $unauth->json('message'));

        Sanctum::actingAs(User::create(['email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B']));
        $missing = $this->call('GET', '/api/v1/feedings/9999');
        $this->assertSame(404, $missing->getStatusCode());
        $this->assertSame('Not found', $missing->json('message'));
        $this->assertSame(404, $this->call('GET', '/api/v1/nope')->getStatusCode());
    }

    public function test_register_validates_and_returns_user_and_token(): void
    {
        $this->postJson('/api/v1/auth/register', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'first_name', 'last_name']);
        $this->postJson('/api/v1/auth/register', ['email' => 'a@b.test', 'password' => 'short', 'first_name' => 'A', 'last_name' => 'B'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $payload = ['email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B'];
        $this->postJson('/api/v1/auth/register', $payload)->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'email'], 'token'])
            ->assertJsonMissingPath('user.password');
        $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_me_and_logout_use_the_issued_token(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B',
        ])->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('email', 'a@b.test');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        // The guard caches the resolved user within one app instance.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_authenticated_api_is_rate_limited(): void
    {
        Sanctum::actingAs(User::create(['email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B']));

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/auth/me')->assertOk();
        }
        $this->getJson('/api/v1/auth/me')->assertStatus(429);
    }
}
