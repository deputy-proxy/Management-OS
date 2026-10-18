<?php

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReadinessController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->select('SELECT 1');
        } catch (Throwable $exception) {
            Log::warning('Readiness check failed', [
                'dependency' => 'database',
                'exception_class' => $exception::class,
            ]);

            return response()->json(['status' => 'not_ready'], 503);
        }

        return response()->json(['status' => 'ready']);
    }
}
