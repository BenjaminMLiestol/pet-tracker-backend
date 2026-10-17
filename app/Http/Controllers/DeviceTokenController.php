<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /** Register (or re-claim) this phone's push token for the signed-in user. */
    public function store(Request $request)
    {
        $data = $request->validate([
            // Expo push tokens look like ExponentPushToken[xxxx] (older: ExpoPushToken[xxxx]).
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[^\]]+\]$/'],
            'platform' => ['nullable', 'in:ios,android'],
        ]);

        // A phone shared between accounts must only notify whoever is signed in now.
        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? null],
        );

        return response()->json([], 204);
    }

    /** Forget this phone (on logout). Only your own tokens can be removed. */
    public function destroy(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string']]);
        $request->user()->deviceTokens()->where('token', $data['token'])->delete();

        return response()->json([], 204);
    }
}
