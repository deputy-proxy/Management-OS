<?php

namespace App\Http\Controllers;

use App\Models\EmployeePaySlip;
use App\Models\User;
use App\Services\Payroll\PayrollPeriodExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollPeriodExportController
{
    public function __invoke(
        Request $request,
        EmployeePaySlip $paySlip,
        string $type,
        string $format,
        PayrollPeriodExportService $service,
    ): StreamedResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $runId = $request->integer('run');
        abort_unless($runId > 0, 422, 'A calculation run ID is required.');

        $export = $service->export($actor, $paySlip, $runId, $type, $format);

        return response()->streamDownload(
            static function () use ($export): void {
                echo $export['content'];
            },
            $export['filename'],
            [
                'Content-Type' => $export['media_type'],
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
