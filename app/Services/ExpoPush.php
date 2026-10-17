<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends push notifications through Expo's relay. Best effort: a failed push never breaks
 * the request that caused it, and tokens Expo reports as dead are forgotten.
 */
class ExpoPush
{
    private const BATCH = 100;

    /**
     * @param  Collection<int, User>  $users
     * @param  callable(User): array{title: string, body: string}  $message  built per person (language)
     * @param  array<string, mixed>  $data  delivered to the app with the notification
     */
    public function send(Collection $users, callable $message, array $data = []): void
    {
        $messages = [];
        foreach ($users->load('deviceTokens') as $user) {
            foreach ($user->deviceTokens as $device) {
                $messages[] = ['to' => $device->token, 'sound' => 'default', 'data' => $data] + $message($user);
            }
        }

        foreach (array_chunk($messages, self::BATCH) as $batch) {
            $this->deliver($batch);
        }
    }

    private function deliver(array $batch): void
    {
        try {
            $request = Http::acceptJson()->timeout(5);
            if ($token = config('services.expo.access_token')) {
                $request = $request->withToken($token);
            }
            $response = $request->post(config('services.expo.push_url'), $batch);
            if ($response->failed()) {
                Log::warning('Expo push failed', ['status' => $response->status()]);

                return;
            }

            // One ticket per message, in order. Drop tokens whose app was uninstalled.
            foreach ($response->json('data', []) as $i => $ticket) {
                if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    DeviceToken::where('token', $batch[$i]['to'])->delete();
                }
            }
        } catch (Throwable $e) {
            Log::warning('Expo push error', ['message' => $e->getMessage()]);
        }
    }
}
