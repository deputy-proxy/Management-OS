<?php

use App\Http\Controllers\Mcp\McpController;
use Illuminate\Support\Facades\Route;

Route::any('/mcp', McpController::class)
    ->middleware('throttle:60,1')
    ->name('mcp.endpoint');
