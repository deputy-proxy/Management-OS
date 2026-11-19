<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireMcpPkceS256
{
    public function handle(Request $request, Closure $next): Response
    {
        $challenge = $request->query('code_challenge');
        if ($request->query('response_type') !== 'code'
            || $request->query('code_challenge_method') !== 'S256'
            || ! is_string($challenge)
            || preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge) !== 1) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Authorization code flow requires a valid S256 PKCE code challenge.',
            ], 400);
        }

        return $next($request);
    }
}
