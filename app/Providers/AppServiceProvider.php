<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('inscription', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
    }
}
