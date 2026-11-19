<?php

namespace App\Providers;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Contracts\KpiAlertDeliveryAdapter;
use App\Services\DatabaseKpiAlertDeliveryAdapter;
use App\Services\GoogleAds\GoogleAdsRestCampaignMetricsAdapter;
use App\Services\MarketIntelligence\MarketDataSourceRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Application-wide bindings belong here.
        $this->app->singleton(MarketDataSourceRegistry::class);
        $this->app->bind(GoogleAdsCampaignMetricsAdapter::class, GoogleAdsRestCampaignMetricsAdapter::class);
        $this->app->bind(KpiAlertDeliveryAdapter::class, DatabaseKpiAlertDeliveryAdapter::class);
    }

    public function boot(): void
    {
        Passport::authorizationView('mcp.authorize');
        Passport::tokensCan(['mcp:use' => 'Access the Management-OS MCP server.']);
        Passport::tokensExpireIn(now()->addMinutes((int) config('mcp.access_token_ttl_minutes', 60)));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('mcp.refresh_token_ttl_days', 30)));

        Queue::looping(function (): void {
            try {
                Cache::put('ops:heartbeat:queue-worker', now()->timestamp, now()->addMinutes(5));
            } catch (Throwable $exception) {
                Log::warning('Queue worker heartbeat could not be recorded', [
                    'exception_class' => $exception::class,
                ]);
            }
        });

        RateLimiter::for('api-v1', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });
    }
}
