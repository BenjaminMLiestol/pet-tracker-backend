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

    public function test_reminder_times_default_update_and_validate(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B',
        ])->assertCreated()
            ->assertJsonPath('user.feeding_reminder_time', '18:00')
            ->assertJsonPath('user.walk_reminder_time', '19:00')
            ->json('token');

        $this->withToken($token)->patchJson('/api/v1/auth/me', [
            'feeding_reminder_time' => '07:30', 'walk_reminder_time' => '21:05',
        ])->assertOk()
            ->assertJsonPath('feeding_reminder_time', '07:30')
            ->assertJsonPath('walk_reminder_time', '21:05');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertJsonPath('feeding_reminder_time', '07:30');

        foreach (['25:00', '7:30', '18:60', 'evening', '18:00:00'] as $bad) {
            $this->withToken($token)->patchJson('/api/v1/auth/me', ['walk_reminder_time' => $bad])
                ->assertUnprocessable()->assertJsonValidationErrors('walk_reminder_time');
        }
    }

    public function test_reminder_times_are_personal_to_each_user(): void
    {
        $a = User::create(['email' => 'a@b.test', 'password' => 'secret123', 'first_name' => 'A', 'last_name' => 'B']);
        $b = User::create(['email' => 'c@d.test', 'password' => 'secret123', 'first_name' => 'C', 'last_name' => 'D']);

        Sanctum::actingAs($a);
        $this->patchJson('/api/v1/auth/me', ['feeding_reminder_time' => '06:00'])->assertOk();

        Sanctum::actingAs($b);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('feeding_reminder_time', '18:00');
    }

    /** Calls the API with a real bearer token (Sanctum::actingAs would skip token expiry). */
    private function apiAs(string $token, string $method, string $uri): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards(); // the guard remembers the previous request's user

        return $this->withToken($token)->json($method, $uri);
    }

    private function signIn(): string
    {
        User::create(['email' => 'a@b.test', 'password' => bcrypt('secret123'), 'first_name' => 'A', 'last_name' => 'B']);

        return $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test', 'password' => 'secret123'])->json('token');
    }

    public function test_a_login_lasts_a_week(): void
    {
        $token = $this->signIn();

        $this->assertEquals(now()->addDays(7)->startOfMinute(), \Laravel\Sanctum\PersonalAccessToken::first()->expires_at->startOfMinute());

        $this->travelTo(now()->addDays(7)->addMinute());
        $this->apiAs($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_refreshing_swaps_in_a_fresh_week_and_keeps_the_old_login_for_a_moment(): void
    {
        $old = $this->signIn();
        $this->travel(6)->days();

        $new = $this->apiAs($old, 'POST', '/api/v1/auth/refresh')->assertOk()->assertJsonStructure(['user', 'token'])->json('token');
        $this->assertNotSame($old, $new);

        // Both work right after (a lost reply must not log anyone out)...
        $this->apiAs($old, 'GET', '/api/v1/auth/me')->assertOk();
        $this->apiAs($new, 'GET', '/api/v1/auth/me')->assertOk();

        // ...but the old one is gone after the grace period, while the new one lasts a week.
        $this->travel(10)->minutes();
        $this->apiAs($old, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->travel(5)->days();
        $this->apiAs($new, 'GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_refresh_needs_a_valid_login(): void
    {
        $this->postJson('/api/v1/auth/refresh')->assertUnauthorized();
        $token = $this->signIn();
        $this->travel(8)->days();

        $this->apiAs($token, 'POST', '/api/v1/auth/refresh')->assertUnauthorized();
    }

    public function test_sign_out_everywhere_ends_every_session(): void
    {
        $first = $this->signIn();
        $second = $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test', 'password' => 'secret123'])->json('token');

        $this->apiAs($first, 'POST', '/api/v1/auth/logout-all')->assertOk();

        $this->apiAs($first, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->apiAs($second, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }
}
