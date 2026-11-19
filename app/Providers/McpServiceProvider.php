<?php

namespace App\Providers;

use App\Mcp\Server\McpServerFactory;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            McpServerFactory::class,
            fn ($app): McpServerFactory => new McpServerFactory($app->tagged('mcp.capability_registrars')),
        );
    }
}
