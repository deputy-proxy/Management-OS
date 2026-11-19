<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireMcpResource
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedResource = url('/mcp');
        $resource = $request->input('resource');

        if (! is_string($resource) || ! hash_equals($expectedResource, $resource)) {
            return response()->json([
                'error' => 'invalid_target',
                'error_description' => 'The resource parameter must identify this MCP server.',
            ], 400);
        }

        $request->attributes->set('mcp_resource', $expectedResource);
        $response = $next($request);

        if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
            $payload = json_decode((string) $response->getContent(), true);

            if (is_array($payload) && isset($payload['access_token'])) {
                $payload['resource'] = $expectedResource;
                $response->setContent(json_encode($payload, JSON_THROW_ON_ERROR));
            }
        }

        return $response;
    }
}
