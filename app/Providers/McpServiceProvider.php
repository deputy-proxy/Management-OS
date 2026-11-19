<?php

namespace App\Providers;

use App\Mcp\Authorization\AuthorizedMcpCapabilityRegistrar;
use App\Mcp\Server\McpServerFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ((array) config('mcp.capability_registrars', []) as $registrar) {
            if (! is_string($registrar) || ! is_subclass_of($registrar, AuthorizedMcpCapabilityRegistrar::class)) {
                throw new InvalidArgumentException('Every MCP capability registrar must extend AuthorizedMcpCapabilityRegistrar.');
            }

            $this->app->tag([$registrar], 'mcp.capability_registrars');
        }

        $this->app->singleton(
            McpServerFactory::class,
            fn ($app): McpServerFactory => new McpServerFactory($app->tagged('mcp.capability_registrars')),
        );
    }
}
