# Management-OS MCP server

## Current implementation status

The repository exposes a remote Model Context Protocol (MCP) endpoint at `/mcp` using Streamable HTTP. This issue implements the protocol and transport foundation only.

- The endpoint currently exposes no business tools, resources, or prompts.
- It does not authenticate callers or expose business data. Do not add business handlers or deploy it as a usable management integration until the identity and authorization work in issues #530 and #531 is complete.
- No mutations are enabled. The approval and audit controls in #532 are required before any controlled write is introduced.
- The endpoint is not yet verified against the production reverse proxy/hosting environment or a live ChatGPT workspace.

## Implementation

The transport uses the official PHP MCP SDK `mcp/sdk` v0.10.0. Composer's `composer.lock` pins the resolved SDK source revision. The SDK supports PHP 8.2+, while this application requires PHP 8.4.1 or newer. The SDK is pre-1.0 and describes itself as experimental; review release notes and rerun protocol/security tests before upgrading.

The Laravel boundary is intentionally small:

- `routes/mcp.php` registers `/mcp` in the API middleware group, outside the web/CSRF middleware.
- `McpController` adapts Laravel/Symfony HTTP messages to PSR-7 and runs the SDK's `StreamableHttpTransport`.
- `McpServerFactory` creates the server, configures persistent protocol sessions, and invokes registered capability registrars.
- `McpCapabilityRegistrar` is the extension point for future tool, resource, and prompt registrations. Register implementations through the `mcp.capability_registrars` container tag. Keep handlers separate from transport registration and route all business operations through existing authorized application/domain services.

The SDK currently accepts both the 2025-11-25 initialize/session handshake and the 2026-07-28 stateless request envelope. The integration tests exercise both protocol eras. Do not infer that every MCP client uses the same protocol revision; verify against the target client.

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

The exact production PHP-FPM execution timeout, reverse-proxy buffering/streaming behavior, request-size limit, and multi-instance topology have not been verified. Confirm them in staging before release. The protocol foundation does not override hosting-level request timeouts.

## Local setup and protocol inspection

Use the repository's normal PHP and Node prerequisites. From the repository root:

```sh
cp .env.example .env
composer install --no-interaction --prefer-dist --no-progress
npm ci
php artisan key:generate --force
mkdir -p database && touch database/database.sqlite
php artisan migrate --force
npm run build
MCP_ALLOWED_HOSTS=localhost,127.0.0.1 php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal, inspect the legacy initialize/session handshake:

```sh
npx @modelcontextprotocol/inspector --cli \
  --protocol-era legacy \
  --transport http \
  --server-url http://127.0.0.1:8000/mcp \
  --method initialize
```

Inspect the current stateless protocol discovery:

```sh
npx @modelcontextprotocol/inspector --cli \
  --protocol-era modern \
  --transport http \
  --server-url http://127.0.0.1:8000/mcp \
  --method tools/list
```

The web Inspector can also be launched with `npx @modelcontextprotocol/inspector`; choose Streamable HTTP and enter `http://127.0.0.1:8000/mcp`. The Inspector CLI requires Node.js 22.19.0 or newer. The repository's CI Node 22 line and local runtime should be checked for that minimum when using the current Inspector release.

Expected current result: server identity `Management-OS`, protocol discovery succeeds, and the tool/resource/prompt lists are empty. This is intentional until the later authorization and domain-tool issues are complete.

## ChatGPT connection and authentication constraints

ChatGPT custom MCP connections use a public HTTPS endpoint and support Streamable HTTP. Current custom MCP server connection availability is limited to ChatGPT Business and Enterprise/Edu workspaces; Pro currently has read/fetch-only custom MCP access. Confirm the target workspace's permissions before planning rollout.

The transport endpoint is not ready to connect to a production Management-OS account. Issue #530 must implement and verify the supported OAuth flow before private data or tools are exposed. Current OpenAI guidance requires OAuth 2.1 authorization code with PKCE (`S256`), MCP protected-resource metadata, authorization-server discovery, and an appropriate client registration method (CIMD, DCR, or a predefined client). ChatGPT does not support machine-to-machine OAuth grants such as the client-credentials grant, service-account grant, or JWT bearer assertion. Therefore, reconcile the issue's proposed dedicated AI service identity with user-delegated OAuth before implementation; do not implement a service-account OAuth grant or assume a static API key is supported.

A least-privilege Management-OS principal may still be part of the application's internal authorization design, but it must be mapped to a verified ChatGPT-supported authorization flow. Authenticate and authorize every business tool on the server. ChatGPT conversational confirmation is not a substitute for the independent Management-OS approval workflow required by #532.

## Validation

Focused transport regression tests:

```sh
php artisan test tests/Feature/McpTransportTest.php
```

The test suite covers legacy initialization and persistent session headers, modern stateless discovery, empty business capability lists, unsupported HTTP methods, malformed JSON, DNS-rebinding host rejection, CORS origin restrictions, rate limiting, private session-directory permissions, and request-body limits.

Before the PR, run every validation job defined in `.github/workflows/tests.yml`, including the PHP/SQLite suite, PHPStan/Larastan, MySQL 8.0 migration/integrity tests, frontend production build, and dependency audits. A local pass does not replace inspecting the actual GitHub Actions run. Staging verification must separately confirm HTTPS, reverse-proxy streaming, timeouts, request limits, and ChatGPT's real connection behavior.
