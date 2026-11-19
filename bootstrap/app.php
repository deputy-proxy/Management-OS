<?php

use App\Http\Middleware\AttachRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/mcp.php'));
        },
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AttachRequestId::class);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
        ]);
    })
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => ['web', 'auth'], // Ensure 'web' is first!
        ],
    )
    ->withExceptions(function (Exceptions $exceptions): void {
        // Filament provides the web login route; this app does not define a
        // standalone route named `login`. Passport's browser authorization
        // flow must send unauthenticated users to the Filament login page.
        $exceptions->redirectGuestsTo(
            fn (\Illuminate\Http\Request $request): string => route('filament.admin.auth.login'),
        );
    })->create();
