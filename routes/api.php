<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Employee;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// This will now be accessible at /api/employees/count
Route::get('/employees/count', function () {
    return response()->json([
        'count' => Employee::count()
    ]);
});