<?php

use App\Http\Controllers\Mcp\McpController;
use App\Http\Middleware\RequireMcpPkceS256;
use App\Http\Middleware\RequireMcpResource;
use App\Mcp\Authorization\McpToolPermissionRegistry;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

// Override Laravel MCP's default discovery metadata so clients can request
// every tool-specific scope registered from the same permission matrix.
$scopes = static fn (): array => array_values(array_unique([
    'mcp:use',
    ...array_keys(app(McpToolPermissionRegistry::class)->passportScopes()),
]));

Route::get('/.well-known/oauth-authorization-server', static fn () => response()->json(array_filter([
    'issuer' => config('mcp.authorization_server') ?? url('/'),
    'authorization_endpoint' => route('passport.authorizations.authorize'),
    'token_endpoint' => route('passport.token'),
    'registration_endpoint' => route('mcp.oauth.register'),
    'response_types_supported' => ['code'],
    'code_challenge_methods_supported' => ['S256'],
    'scopes_supported' => $scopes(),
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
], static fn (mixed $value): bool => $value !== null)))->name('mcp.oauth.authorization-server');

Route::get('/.well-known/oauth-protected-resource', static fn () => response()->json([
    'resource' => url('/mcp'),
    'authorization_servers' => [config('mcp.authorization_server') ?? url('/')],
    'scopes_supported' => $scopes(),
]))->name('mcp.oauth.protected-resource');

Mcp::oauthRoutes();

// The package also registers a nested protected-resource endpoint used by
// the MCP WWW-Authenticate challenge. Override it as well, or clients would
// discover only the generic server scope despite the exact metadata being correct.
Route::get('/.well-known/oauth-protected-resource/{path}', static fn (string $path) => response()->json([
    'resource' => url('/'.ltrim($path, '/')),
    'authorization_servers' => [config('mcp.authorization_server') ?? url('/')],
    'scopes_supported' => $scopes(),
]))->where('path', '.*')->name('mcp.oauth.protected-resource.nested');
Route::getRoutes()->getByName('passport.authorizations.authorize')?->middleware([RequireMcpPkceS256::class, RequireMcpResource::class]);
Route::getRoutes()->getByName('passport.token')?->middleware(RequireMcpResource::class);

Route::options('/mcp', McpController::class)->middleware('throttle:60,1')->name('mcp.preflight');
Route::match(['GET', 'DELETE', 'PUT', 'PATCH'], '/mcp', McpController::class)->middleware('throttle:60,1')->name('mcp.unsupported-method');
Route::post('/mcp', McpController::class)->middleware(['throttle:60,1', AddWwwAuthenticateHeader::class, 'auth:api', 'abilities:mcp:use'])->name('mcp.endpoint');
