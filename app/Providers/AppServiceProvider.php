<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Application-wide bindings belong here.
    }

    public function boot(): void
    {
        Queue::looping(function (): void {
            Cache::put('ops:heartbeat:queue-worker', now()->timestamp, now()->addMinutes(5));
        });

        RateLimiter::for('api-v1', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });
    }
}
