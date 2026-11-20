# Management-OS MCP server

## Current implementation status

The repository exposes a remote Model Context Protocol (MCP) endpoint at `/mcp` using Streamable HTTP. The transport foundation from #529 is protected by the Passport OAuth integration in #530, and the authorization boundary from #531 is in place. The capability registry includes six read-only executive tools from #533 and nine read-only finance tools from #534. CRM and operations tools, controlled writes, resources, and prompts remain disabled until their individual issues implement and test their data contracts.

- The endpoint exposes 15 read-only tools: six executive tools and nine finance tools. The finance set is `get_financial_overview`, `get_transactions`, `get_receivables`, `get_payables`, `get_cash_forecast`, `get_budget_vs_actuals`, `get_profitability_report`, `get_reconciliation_status`, and `get_invoice_details`.
- MCP POST requests require a Laravel Passport OAuth access token with the `mcp:use` scope. Each business tool additionally requires its dedicated OAuth tool scope and the configured AI principal; source-level data is filtered by the principal's current HexaLite permissions.
- No mutations are enabled. Issue #532 adds encrypted payload-bound approvals, independent human review, execution audit, idempotency and conservative recovery primitives. Future write tools must use these controls and existing domain services.
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
- The authorization endpoint enforces PKCE S256 and requires the exact `resource=https://<host>/mcp` parameter. The token endpoint requires the same resource value and echoes it in successful token responses. Issued JWTs include the configured issuer and the MCP resource in `aud`; the OAuth client identifier is retained as the first audience entry for Passport's resource-server guard. CORS preflight remains unauthenticated; MCP POST requests are authenticated and scope-checked. Unauthorized responses include a WWW-Authenticate resource-metadata challenge.
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

Expected current result after OAuth authorization: server identity Management-OS, protocol discovery succeeds, and `tools/list` returns 15 read-only executive and finance tools. Resource and prompt lists remain empty. Tool calls additionally require the configured AI principal, the tool-specific OAuth scope, and the relevant domain permissions.

## ChatGPT connection and authentication constraints

ChatGPT custom MCP connections use a public HTTPS endpoint and support Streamable HTTP. Current custom MCP server connection availability is limited to ChatGPT Business and Enterprise/Edu workspaces; Pro currently has read/fetch-only custom MCP access. Confirm the target workspace's permissions before planning rollout.

The Passport provider and OAuth metadata routes are wired, but production readiness is not yet established. Local tests verify discovery, public-client registration, the ChatGPT callback allowlist, authentication denial, scope enforcement, a complete authorization-code/PKCE exchange, refresh-token rotation, replay rejection, and revocation. A live ChatGPT workspace connection remains a staging gate.

The Management-OS OAuth flow now validates the OAuth `resource` parameter on both authorization and token requests, echoes it in successful token responses, and binds issued JWTs to the `/mcp` resource audience while retaining Passport's client audience for token validation. Automated tests cover matching, missing and incorrect resource values, JWT issuer/audience claims, authorization-code exchange, and refresh-token rotation. This closes the implementation gap in the local Passport integration; a live ChatGPT workspace connection is still required to verify interoperability.

OAuth consent is completed by an authenticated Management-OS user, but Passport access tokens are issued with the dedicated AI Assistant principal as their subject. Set `MCP_AI_PRINCIPAL_EMAIL=chatgpt@edventure.ro` and run `php artisan mcp:provision-principal` after migrations. The command requires exactly one global `Assistant` HexaLite role for the active guard, and any assigned domain permissions must be declared by the MCP permission matrix. It creates the account with a random, undisclosed password, no verified email, and assigns the Assistant role; with an empty matrix that role must remain permissionless. For an existing account it verifies that the same role is its only role and rejects any permissions outside the matrix. It refuses missing, duplicate, unmapped, tenant-scoped, or unexpected roles. Keep the role assigned even while it has no permissions: HexaLite treats users with no roles as implicitly authorized by its legacy gate helper. The explicit role assignment avoids that fail-open behavior and does not grant Filament panel access; any permissions it receives later must be mapped to reviewed MCP tools. The #531 permission matrix now limits any permissions assigned to this role to reviewed, tool-mapped domain permissions. The consent page distinguishes the human approver from the AI principal. Never grant Owner/superadmin or copy the human approver's permissions. ChatGPT conversational confirmation is not a substitute for the independent Management-OS approval workflow implemented in #532. For emergency credential invalidation, run `php artisan mcp:revoke-principal-tokens`; it revokes all access and refresh tokens for the configured principal in one database transaction. Reauthorize the connector only after the incident is contained and reviewed.

## Validation

Focused transport regression tests:

```sh
php artisan test tests/Feature/McpTransportTest.php
```

The test suite covers Passport OAuth discovery, PKCE S256 metadata and enforcement, dynamic public-client registration, ChatGPT callback allowlisting, authorization-code exchange, refresh-token rotation, code replay rejection, token revocation, unauthenticated and insufficient-scope denial, legacy initialization, modern stateless discovery, the 15 permission-gated executive and finance tools and empty resource/prompt lists, unsupported HTTP methods, malformed JSON, DNS-rebinding host rejection, CORS origin restrictions, rate limiting, private session-directory permissions, and request-body limits.

Before the PR, run every validation job defined in `.github/workflows/tests.yml`, including the PHP/SQLite suite, PHPStan/Larastan, MySQL 8.0 migration/integrity tests, frontend production build, and dependency audits. A local pass does not replace inspecting the actual GitHub Actions run. Staging verification must separately confirm HTTPS, reverse-proxy streaming, timeouts, request limits, and ChatGPT's real connection behavior.

## Tool authorization

MCP tools must follow the versioned permission matrix and authorization contract in [MCP permission matrix](mcp-permissions.md). Each tool has an explicit OAuth scope, required and optional HexaLite permissions, record scope and output-field allowlist. See [MCP executive tools](mcp-executive-tools.md) and [MCP finance tools](mcp-finance-tools.md) for the read-only tool contracts and their data limitations.


## Approval and execution audit controls

The encrypted approval lifecycle, independent human review, idempotent execution claim, privacy-safe MCP audit trail, expiry and interrupted-execution recovery are documented in [MCP approval and execution audit controls](mcp-approvals-and-audit.md). The Filament review resources require explicit staff permissions. No business mutation tool is registered; a later domain issue must implement each mutation through an existing authorized service and the approval workflow.
