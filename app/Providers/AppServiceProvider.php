<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('scan-api', static function (Request $request): Limit {
            $maxAttempts = (int) config('services.scan.rate_limit_per_minute', 60);
            $key = $request->bearerToken() ?: $request->ip();

            return Limit::perMinute($maxAttempts)->by('scan-api:'.$key);
        });
    }
}
