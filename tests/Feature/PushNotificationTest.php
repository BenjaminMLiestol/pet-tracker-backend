<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ExponentPushToken[abc123]';

    private function user(string $email, string $first = 'T', array $extra = []): User
    {
        return User::create(['email' => $email, 'password' => 'secret123', 'first_name' => $first, 'last_name' => 'U'] + $extra);
    }

    /** A dog owned by Benjamin and Kari; Kari has a phone registered. */
    private function sharedDog(array $kariExtra = []): array
    {
        $ben = $this->user('ben@test.dev', 'Benjamin');
        $kari = $this->user('kari@test.dev', 'Kari', $kariExtra);
        $pet = Pet::create(['name' => 'Toya']);
        $pet->users()->attach([$ben->id, $kari->id]);
        DeviceToken::create(['user_id' => $kari->id, 'token' => self::TOKEN, 'platform' => 'ios']);

        return [$ben, $kari, $pet];
    }

    private function fakeExpo(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
    }

    public function test_registering_a_device_validates_and_reassigns_the_token(): void
    {
        $a = $this->user('a@test.dev');
        $b = $this->user('b@test.dev');

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/devices', ['token' => 'not-a-token'])->assertUnprocessable();
        $this->postJson('/api/v1/devices', ['token' => self::TOKEN, 'platform' => 'ios'])->assertNoContent();
        $this->postJson('/api/v1/devices', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(1, DeviceToken::count());

        // The same phone signs in as someone else: only they get its pushes now.
        Sanctum::actingAs($b);
        $this->postJson('/api/v1/devices', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame($b->id, DeviceToken::first()->user_id);
    }

    public function test_only_your_own_device_can_be_removed(): void
    {
        $a = $this->user('a@test.dev');
        $b = $this->user('b@test.dev');
        DeviceToken::create(['user_id' => $a->id, 'token' => self::TOKEN]);

        Sanctum::actingAs($b);
        $this->deleteJson('/api/v1/devices', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(1, DeviceToken::count());

        Sanctum::actingAs($a);
        $this->deleteJson('/api/v1/devices', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_a_co_owner_is_told_when_the_dog_is_fed(): void
    {
        $this->fakeExpo();
        [$ben, , $pet] = $this->sharedDog();
        Sanctum::actingAs($ben);

        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r[0]['to'] === self::TOKEN
            && $r[0]['title'] === 'Toya'
            && $r[0]['body'] === 'Benjamin matet Toya'
            && $r[0]['data']['pet_id'] === $pet->id);
    }

    public function test_a_co_owner_is_told_when_the_dog_is_walked(): void
    {
        $this->fakeExpo();
        [$ben, , $pet] = $this->sharedDog();
        Sanctum::actingAs($ben);

        $this->postJson('/api/v1/walks', ['pet_id' => $pet->id])->assertCreated();

        Http::assertSent(fn ($r) => $r[0]['body'] === 'Benjamin gikk tur med Toya');
    }

    public function test_message_follows_the_receivers_language(): void
    {
        $this->fakeExpo();
        [$ben, , $pet] = $this->sharedDog(['locale' => 'en']);
        Sanctum::actingAs($ben);

        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated();

        Http::assertSent(fn ($r) => $r[0]['body'] === 'Benjamin fed Toya');
    }

    public function test_nobody_is_pinged_for_your_own_action_or_when_opted_out(): void
    {
        $this->fakeExpo();
        [$ben, $kari, $pet] = $this->sharedDog();
        DeviceToken::create(['user_id' => $ben->id, 'token' => 'ExponentPushToken[ben]']);

        // Kari opts out; Benjamin is the one acting, so there is nobody left to tell.
        $kari->update(['notify_co_owners' => false]);
        Sanctum::actingAs($ben);
        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated();

        Http::assertNothingSent();
    }

    public function test_backdated_entries_and_solo_dogs_stay_quiet(): void
    {
        $this->fakeExpo();
        [$ben, , $pet] = $this->sharedDog();
        $solo = Pet::create(['name' => 'Solo']);
        $solo->users()->attach($ben);
        Sanctum::actingAs($ben);

        // Logging last night's walk, or undoing a delete, isn't news.
        $this->postJson('/api/v1/walks', ['pet_id' => $pet->id, 'walked_at' => now()->subHours(3)->toIso8601String()])->assertCreated();
        $this->postJson('/api/v1/feedings', ['pet_id' => $solo->id])->assertCreated();

        Http::assertNothingSent();
    }

    public function test_dead_tokens_are_forgotten(): void
    {
        [$ben, , $pet] = $this->sharedDog();
        Sanctum::actingAs($ben);
        Http::fake(['exp.host/*' => Http::response(['data' => [
            ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);

        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated();

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_a_failing_relay_does_not_break_logging(): void
    {
        [$ben, , $pet] = $this->sharedDog();
        Sanctum::actingAs($ben);
        Http::fake(['exp.host/*' => Http::response('boom', 500)]);

        $this->postJson('/api/v1/feedings', ['pet_id' => $pet->id])->assertCreated();

        $this->assertSame(1, DeviceToken::count());
    }

    public function test_push_settings_are_saved_on_the_profile(): void
    {
        $me = $this->user('me@test.dev');
        Sanctum::actingAs($me);

        $this->patchJson('/api/v1/auth/me', ['notify_co_owners' => false, 'locale' => 'en'])
            ->assertOk()->assertJsonPath('notify_co_owners', false)->assertJsonPath('locale', 'en');
        $this->patchJson('/api/v1/auth/me', ['locale' => 'xx'])->assertUnprocessable();
        $this->getJson('/api/v1/auth/me')->assertJsonPath('notify_co_owners', false);
    }
}
