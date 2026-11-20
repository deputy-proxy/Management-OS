<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CaptureMcpAuditContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // This middleware runs only after Passport's auth:api middleware. The signed
        // token has therefore already been validated before its client claim is read.
        $segments = explode('.', (string) $request->bearerToken());

        if (count($segments) === 3) {
            $encodedPayload = strtr($segments[1], '-_', '+/');
            $encodedPayload .= str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4);
            $payload = base64_decode($encodedPayload, true);
            $claims = is_string($payload) ? json_decode($payload, true) : null;
            $clientId = is_array($claims) ? ($claims['client_id'] ?? null) : null;

            if (is_string($clientId) && Str::isUuid($clientId)) {
                $request->attributes->set('oauth_client_id', $clientId);
            }
        }

        return $next($request);
    }
}
