<?php

$applicationHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
$configuredHosts = trim((string) env('MCP_ALLOWED_HOSTS', ''));
$configuredOrigins = trim((string) env('MCP_ALLOWED_ORIGINS', ''));
$configuredRedirectDomains = trim((string) env('MCP_OAUTH_REDIRECT_DOMAINS', 'https://chatgpt.com'));
$authorizationServer = trim((string) env('MCP_AUTHORIZATION_SERVER', ''));

return [
    'authorization_server' => $authorizationServer === '' ? null : rtrim($authorizationServer, '/'),
    'ai_principal_email' => env('MCP_AI_PRINCIPAL_EMAIL'),
    'permission_matrix_version' => '1.0.0',
    'domain_permission_guard' => env('MCP_DOMAIN_PERMISSION_GUARD', config('auth.defaults.guard', 'web')),
    'principal_role' => 'Assistant',
    // Add every exposed tool here. Missing definitions are denied by default.
    'tool_permissions' => [],
    'access_token_ttl_minutes' => (int) env('MCP_ACCESS_TOKEN_TTL_MINUTES', 60),
    'refresh_token_ttl_days' => (int) env('MCP_REFRESH_TOKEN_TTL_DAYS', 30),
    'redirect_domains' => array_values(array_filter(array_map('trim', explode(',', $configuredRedirectDomains)))),
    'server_name' => env('MCP_SERVER_NAME', 'Management-OS'),
    'server_version' => env('MCP_SERVER_VERSION', '0.1.0'),
    'max_body_bytes' => (int) env('MCP_MAX_BODY_BYTES', 1048576),
    'request_timeout_seconds' => (int) env('MCP_REQUEST_TIMEOUT_SECONDS', 25),
    'pagination_limit' => (int) env('MCP_PAGINATION_LIMIT', 50),
    'allowed_hosts' => $configuredHosts === ''
        ? [$applicationHost]
        : array_values(array_filter(array_map('trim', explode(',', $configuredHosts)))),
    'allowed_origins' => $configuredOrigins === ''
        ? []
        : array_values(array_filter(array_map('trim', explode(',', $configuredOrigins)))),
    'session_path' => env('MCP_SESSION_PATH') ?: storage_path('framework/cache/mcp-sessions'),
    'session_ttl_seconds' => (int) env('MCP_SESSION_TTL_SECONDS', 3600),
    'session_lock_timeout_seconds' => (float) env('MCP_SESSION_LOCK_TIMEOUT_SECONDS', 5),
    'approval_ttl_minutes' => (int) env('MCP_APPROVAL_TTL_MINUTES', 15),
    'approval_max_payload_bytes' => (int) env('MCP_APPROVAL_MAX_PAYLOAD_BYTES', 65536),
    'execution_stale_minutes' => (int) env('MCP_EXECUTION_STALE_MINUTES', 5),
];
