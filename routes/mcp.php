<?php

use App\Http\Controllers\Mcp\McpController;
use App\Http\Middleware\RequireMcpPkceS256;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

Mcp::oauthRoutes();
Route::getRoutes()->getByName('passport.authorizations.authorize')?->middleware(RequireMcpPkceS256::class);

Route::options('/mcp', McpController::class)->middleware('throttle:60,1')->name('mcp.preflight');
Route::match(['GET', 'DELETE', 'PUT', 'PATCH'], '/mcp', McpController::class)->middleware('throttle:60,1')->name('mcp.unsupported-method');
Route::post('/mcp', McpController::class)->middleware(['throttle:60,1', AddWwwAuthenticateHeader::class, 'auth:api', 'abilities:mcp:use'])->name('mcp.endpoint');
