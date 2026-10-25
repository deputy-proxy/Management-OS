<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Finance\IssuedInvoiceArtifactAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssuedInvoiceArtifactDownloadController extends Controller
{
    public function __invoke(Request $request, IssuedInvoiceArtifactAccessService $artifacts, int $artifact): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $download = $artifacts->read($artifact, $actor);

        return response()->streamDownload(
            static function () use ($download): void {
                echo $download['content'];
            },
            $download['filename'],
            [
                'Content-Type' => $download['media_type'],
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
        );
    }
}
