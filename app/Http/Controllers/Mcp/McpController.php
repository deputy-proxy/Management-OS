<?php

namespace App\Http\Controllers\Mcp;

use App\Mcp\Server\McpServerFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class McpController
{
    public function __construct(private readonly McpServerFactory $servers) {}

    public function __invoke(Request $request): Response
    {
        $this->applyRequestTimeout();

        try {
            $psr17Factory = new Psr17Factory;
            $psrHttpFactory = new PsrHttpFactory(
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
            );

            $allowedOrigins = (array) config('mcp.allowed_origins', []);
            $transport = new StreamableHttpTransport(
                request: $psrHttpFactory->createRequest($request),
                responseFactory: $psr17Factory,
                streamFactory: $psr17Factory,
                logger: Log::channel(),
                middleware: [
                    new CorsMiddleware(allowedOrigins: $allowedOrigins),
                    new DnsRebindingProtectionMiddleware(
                        allowedHosts: (array) config('mcp.allowed_hosts', ['localhost']),
                        allowedOrigins: $allowedOrigins,
                        responseFactory: $psr17Factory,
                        streamFactory: $psr17Factory,
                    ),
                ],
                maxBodyBytes: (int) config('mcp.max_body_bytes', StreamableHttpTransport::DEFAULT_MAX_BODY_BYTES),
            );

            $psrResponse = $this->servers->create()->run($transport);
            $response = (new HttpFoundationFactory)->createResponse($psrResponse, streamed: true);
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        } catch (Throwable $exception) {
            Log::error('MCP transport request failed.', [
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal server error.',
                ],
                'id' => null,
            ], 500)->header('Cache-Control', 'no-store');
        }
    }

    private function applyRequestTimeout(): void
    {
        if (PHP_SAPI === 'cli' || ! function_exists('set_time_limit')) {
            return;
        }

        $configuredTimeout = (int) config('mcp.request_timeout_seconds', 25);
        if ($configuredTimeout < 1) {
            return;
        }

        $phpTimeout = (int) ini_get('max_execution_time');
        $effectiveTimeout = $phpTimeout > 0
            ? min($phpTimeout, $configuredTimeout)
            : $configuredTimeout;

        @set_time_limit($effectiveTimeout);
    }
}
