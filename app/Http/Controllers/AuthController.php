<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // A login lasts a week. The app swaps it for a fresh one each time it opens, so people
    // who use it stay signed in and a lost phone goes quiet by itself.
    public const TOKEN_DAYS = 7;

    // The swapped-out token keeps working briefly, so a refresh whose reply never arrived
    // (a dropped connection) doesn't sign the person out.
    public const REFRESH_GRACE_MINUTES = 5;

    private function issueToken(User $user): string
    {
        return $user->createToken('mobile', ['*'], now()->addDays(self::TOKEN_DAYS))->plainTextToken;
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
        ]);

        $token = $this->issueToken($user);

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $this->issueToken($user);

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $token = $this->issueToken($user);
        $graceEnds = now()->addMinutes(self::REFRESH_GRACE_MINUTES);
        if ($current->expires_at === null || $current->expires_at->gt($graceEnds)) {
            $current->forceFill(['expires_at' => $graceEnds])->save();
        }

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function me(Request $request)
    {
        return $request->user();
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', Password::min(8)],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            // When to be reminded, 24h "HH:MM". Personal: co-owners can differ.
            'feeding_reminder_time' => ['sometimes', 'date_format:H:i'],
            'walk_reminder_time' => ['sometimes', 'date_format:H:i'],
            'notify_co_owners' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'in:nb,en'],
        ]);
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);

        return $user;
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([], 204);
    }

    /** Sign out everywhere: every phone and session of this person. */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out everywhere']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
