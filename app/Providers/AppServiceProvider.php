<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep the JSON flat ({...}, not {"data": {...}}) so existing clients are unaffected.
        JsonResource::withoutWrapping();

        // General limit for authenticated API traffic: 120 requests/minute per user.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Invitation codes are short: limit guessing to 10 tries a minute per person.
        RateLimiter::for('invite-accept', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // Brute-force protection for login/register: 5 attempts/minute per email + IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
