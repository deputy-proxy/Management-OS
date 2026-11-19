# Management-OS MCP server

## Current implementation status

The repository exposes a remote Model Context Protocol (MCP) endpoint at `/mcp` using Streamable HTTP. The transport foundation from #529 is now protected by the Passport OAuth integration in #530. The server remains intentionally empty until domain authorization is completed.

- The endpoint currently exposes no business tools, resources, or prompts.
- MCP POST requests require a Laravel Passport OAuth access token with the mcp:use scope. No business data is exposed; do not add business handlers until issue #531 completes domain authorization.
- No mutations are enabled. The approval and audit controls in #532 are required before any controlled write is introduced.
- The endpoint is not yet verified against the production reverse proxy/hosting environment or a live ChatGPT workspace.

## Implementation

The transport uses the official PHP MCP SDK `mcp/sdk` v0.10.0. Composer's `composer.lock` pins the resolved SDK source revision. The SDK supports PHP 8.2+, while this application requires PHP 8.4.1 or newer. The SDK is pre-1.0 and describes itself as experimental; review release notes and rerun protocol/security tests before upgrading.


OAuth uses the same provider and Laravel integration as deputy-proxy/cr8or:

- laravel/passport provides the OAuth 2 authorization server and token validation.
- laravel/mcp provides protected-resource metadata, authorization-server discovery, and dynamic client registration through Mcp::oauthRoutes().
- The api guard uses Passport; MCP POST requests require auth:api and the mcp:use scope.
- OAuth clients are public authorization-code clients. The ChatGPT callback allowlist defaults to https://chatgpt.com; configure MCP_OAUTH_REDIRECT_DOMAINS as an explicit comma-separated allowlist. Never use a wildcard.
- Access tokens expire after 60 minutes and refresh tokens after 30 days by default.
- Generate Passport signing keys once per environment with php artisan passport:keys --force. Keep the private key outside source control.
- The authorization endpoint enforces PKCE S256. CORS preflight remains unauthenticated; MCP POST requests are authenticated and scope-checked. Unauthorized responses include a WWW-Authenticate resource-metadata challenge.
- The current lockfile resolves laravel/passport v13.12.0 and laravel/mcp v1.2.1; CR8OR uses the same Passport provider with earlier package releases.

This follows CR8OR's Passport provider choice, not its separate machine-to-machine integration grant. ChatGPT uses authorization-code + PKCE; client-credentials is not exposed through MCP dynamic registration.

The Laravel boundary is intentionally small:

- `routes/mcp.php` registers `/mcp` in the API middleware group, outside the web/CSRF middleware.
- `McpController` adapts Laravel/Symfony HTTP messages to PSR-7 and runs the SDK's `StreamableHttpTransport`.
- `McpServerFactory` creates the server, configures persistent protocol sessions, and invokes registered capability registrars.
- `McpCapabilityRegistrar` is the extension point for future tool, resource, and prompt registrations. Register implementations through the `mcp.capability_registrars` container tag. Keep handlers separate from transport registration and route all business operations through existing authorized application/domain services.

The SDK currently accepts both the 2025-11-25 initialize/session handshake and the 2026-07-28 stateless request envelope. The integration tests exercise both protocol eras. Do not infer that every MCP client uses the same protocol revision; verify against the target client.

## Verified Hostinger runtime snapshot

A read-only Hostinger account inspection on 2026-11-19 for `edv.scl.ro` confirmed:

- The website is enabled and currently runs PHP 8.5.12; PHP 8.4 and 8.5 are available. PHP 8.5.12 satisfies the repository's `^8.4.1` requirement and the SDK/PSR bridge constraints.
- The required runtime extensions are enabled, including BCMath, cURL, DOM, Fileinfo, GD, Intl, mbstring, the MySQL PDO driver (`nd_pdo_mysql`), PDO SQLite, XML, XSL and ZIP. OpenSSL is built in.
- SSL is active and the HTTPS redirect is enabled.
- Hostinger CDN is enabled; the website's server cache is disabled.
- The configured PHP `max_execution_time` falls back to 300 seconds (Hostinger plan maximum 480 seconds). The MCP controller applies its 25-second application cap on non-CLI SAPIs; the lower app cap does not raise the hosting limit.

This verifies the configured PHP baseline and HTTPS certificate/redirect settings, not the actual `/mcp` request lifecycle. CDN/proxy buffering of SSE or streamed responses, effective PHP-FPM timeout behavior, and the hosting topology still require a deployed staging test. No production deployment or hosting setting was changed for this issue.

## Transport safeguards and configuration

- Request bodies are limited to 1 MiB by default (`MCP_MAX_BODY_BYTES`). The SDK rejects oversized bodies with HTTP 413.
- On non-CLI SAPIs, the controller caps PHP execution time to 25 seconds by default (`MCP_REQUEST_TIMEOUT_SECONDS`), without raising a lower `max_execution_time` configured by the host. PHP/hosting and reverse-proxy limits still need staging verification, especially for blocking I/O.
- The endpoint is limited to 60 requests per minute per source IP.
- Host headers are checked against `MCP_ALLOWED_HOSTS`. When unset, the allowlist contains the hostname from `APP_URL`.
- Cross-origin browser access is disabled by default. Set `MCP_ALLOWED_ORIGINS` only to explicit trusted origins when a browser-based client requires it.
- Responses use `Cache-Control: no-store`; request correlation uses the application's existing request-ID middleware.
- The SDK's CORS and DNS-rebinding middleware remain enabled. Do not replace them with an empty middleware list or a wildcard host/origin allowlist without a reviewed security requirement.
- Malformed protocol messages, unsupported HTTP methods, and unexpected internal exceptions do not expose application paths or exception details to the caller.

Legacy handshake sessions are stored as files under `storage/framework/cache/mcp-sessions` by default. The SDK's file session store and `flock()` session lock support concurrent PHP workers sharing one local filesystem. Sessions expire after 3,600 seconds (`MCP_SESSION_TTL_SECONDS`); garbage collection runs probabilistically. The application creates it with mode `0700` and requires it to be writable by the PHP process. If the application is deployed across multiple hosts/containers with non-shared filesystems, replace this with a shared PSR-16 session store and a distributed lock before routing requests across nodes. Do not use an in-memory session store under PHP-FPM.

The effective production PHP-FPM execution timeout, reverse-proxy/CDN buffering and streaming behavior, request-size handling at the edge, and multi-instance topology have not been verified end-to-end. Confirm them in staging before release. The protocol foundation does not override hosting-level request timeouts.

## Local setup and protocol inspection

Use the repository's normal PHP and Node prerequisites. From the repository root:

    cp .env.example .env
    composer install --no-interaction --prefer-dist --no-progress
    npm ci
    php artisan key:generate --force
    php artisan passport:keys --force
    mkdir -p database && touch database/database.sqlite
    php artisan migrate --force
    npm run build
    MCP_ALLOWED_HOSTS=localhost,127.0.0.1 php artisan serve --host=127.0.0.1 --port=8000

The MCP POST endpoint requires an OAuth bearer token with the mcp:use scope. The feature suite exercises OAuth discovery, dynamic registration, the full authorization-code exchange with PKCE S256, refresh-token rotation, code replay rejection, scope checks, and token revocation:

    php artisan test tests/Feature/McpTransportTest.php tests/Feature/McpOAuthTest.php

To connect the web MCP Inspector, launch it with npx @modelcontextprotocol/inspector, choose Streamable HTTP, and enter http://127.0.0.1:8000/mcp. Use its OAuth authorization flow. Add only the exact callback origin shown by the Inspector to MCP_OAUTH_REDIRECT_DOMAINS in the local .env file; do not commit that local override or use a wildcard. The Inspector CLI's unauthenticated commands are not suitable for this protected endpoint.

Expected current result after OAuth authorization: server identity Management-OS, protocol discovery succeeds, and the tool/resource/prompt lists are empty. This is intentional until the later domain-tool issues are complete.

## ChatGPT connection and authentication constraints

ChatGPT custom MCP connections use a public HTTPS endpoint and support Streamable HTTP. Current custom MCP server connection availability is limited to ChatGPT Business and Enterprise/Edu workspaces; Pro currently has read/fetch-only custom MCP access. Confirm the target workspace's permissions before planning rollout.

The Passport provider and OAuth metadata routes are wired, but production readiness is not yet established. Local tests verify discovery, public-client registration, the ChatGPT callback allowlist, authentication denial, scope enforcement, a complete authorization-code/PKCE exchange, refresh-token rotation, replay rejection, and revocation. A live ChatGPT workspace connection remains a staging gate.

OpenAI's current guidance also requires the authorization server to echo the OAuth resource parameter and issue an access token bound to the MCP resource audience. The Laravel MCP + Passport integration in CR8OR does not implement that resource-indicator/audience behavior out of the box, and neither does the current Management-OS integration. Do not claim ChatGPT OAuth compatibility until this gap is implemented and verified end-to-end.

Passport authorization associates the token with the Management-OS user who completes the interactive login/consent flow. A dedicated technical user can be used as that principal, but it has not been provisioned: the system owner must confirm the exact account email and its least-privilege HexaLite permissions. Do not create a guessed technical account or grant Owner/superadmin access. ChatGPT conversational confirmation is not a substitute for the independent Management-OS approval workflow required by #532.

## Validation

Focused transport regression tests:

```sh
php artisan test tests/Feature/McpTransportTest.php
```

The test suite covers Passport OAuth discovery, PKCE S256 metadata and enforcement, dynamic public-client registration, ChatGPT callback allowlisting, authorization-code exchange, refresh-token rotation, code replay rejection, token revocation, unauthenticated and insufficient-scope denial, legacy initialization, modern stateless discovery, empty business capability lists, unsupported HTTP methods, malformed JSON, DNS-rebinding host rejection, CORS origin restrictions, rate limiting, private session-directory permissions, and request-body limits.

Before the PR, run every validation job defined in `.github/workflows/tests.yml`, including the PHP/SQLite suite, PHPStan/Larastan, MySQL 8.0 migration/integrity tests, frontend production build, and dependency audits. A local pass does not replace inspecting the actual GitHub Actions run. Staging verification must separately confirm HTTPS, reverse-proxy streaming, timeouts, request limits, and ChatGPT's real connection behavior.
