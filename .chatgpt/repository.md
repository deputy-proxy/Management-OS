# Repository-specific development configuration

Last verified: 2026-11-19. Re-check the live workflows and manifests before every implementation; this file is a snapshot, not a substitute for inspection.

## Runtime and framework
- Composer PHP requirement: `^8.4.1`; Composer dependency resolution is pinned to PHP 8.4.1 and CI runs the PHP 8.4 line.
- Laravel: 13.x (`^13.0`).
- Filament: 5.x (`^5.11.4`).
- Livewire: 4.x (resolved version is recorded in `composer.lock`).
- Node.js: 22.
- Production Hostinger site `edv.scl.ro` runs PHP 8.5.12 (read-only account check 2026-11-19); PHP 8.4 and 8.5 are available. This satisfies the Composer `^8.4.1` requirement.
- Frontend package manager: npm, using committed `package-lock.json`.
- SQLite CI database for the main PHP job.
- MySQL 8.0 for database migration and integrity regression tests.
- Java 21 for pinned CIUS-RO Schematron validation.

## MCP transport and OAuth integration

- Endpoint: root path /mcp, registered from routes/mcp.php in the API middleware group (not the web/CSRF group).
- Transport SDK: official mcp/sdk v0.10.0, with the exact source revision locked in composer.lock. The SDK is pre-1.0/experimental; upgrade only after compatibility and regression review.
- OAuth provider: Laravel Passport, matching the provider used in deputy-proxy/cr8or; Laravel MCP registers OAuth discovery, protected-resource metadata, and dynamic client registration.
- Current lockfile resolves laravel/passport v13.12.0 and laravel/mcp v1.2.1. The ChatGPT MCP route uses Passport's api guard and requires the mcp:use ability.
- Public authorization-code clients must use PKCE S256. The callback domain allowlist defaults to https://chatgpt.com; configure explicit domains only. Access tokens default to 60 minutes and refresh tokens to 30 days.
- Passport keys are environment secrets. Generate once per environment with php artisan passport:keys --force; never commit private keys.
- Passport OAuth consent uses resources/views/mcp/authorize.blade.php. ChatGPT OAuth authorization maps to the Management-OS user who completes the interactive login/consent flow.
- Local feature tests exercise OAuth discovery, DCR allowlisting, S256 enforcement, complete authorization-code exchange, refresh-token rotation, code replay rejection, scope checks, and token revocation.
- Known compatibility gate: the current Laravel MCP + Passport integration does not echo the OAuth resource parameter or bind issued access tokens to the MCP resource audience. This must be implemented and tested against a real ChatGPT workspace before claiming compatibility.
- No dedicated AI technical user has been provisioned; confirm its exact email and least-privilege HexaLite permissions with the system owner. Never grant Owner/superadmin.
- No business tools, resources, or prompts are registered. Do not expose business data until #531 is complete; do not enable writes before #532.
- Transport defaults: 1 MiB body limit, 25-second PHP execution cap on non-CLI SAPIs, 60 requests/minute/IP, Host allowlist derived from APP_URL, and no cross-origin browser access unless explicitly configured. See docs/mcp-server.md for OAuth setup, environment settings, deployment caveats, and release gates.
- Hostinger confirms active SSL/HTTPS redirect, CDN enabled, server cache disabled, and PHP max_execution_time default 300 seconds (plan maximum 480). The MCP application caps non-CLI requests at 25 seconds. CDN/proxy streaming, effective PHP-FPM behavior, edge request-size handling, and multi-instance topology remain unverified and require staging validation.

## Current CI validation
Source: `.github/workflows/tests.yml`.

- PHP job: Composer validation, documented-script verification, Composer install, Laravel environment setup, frontend build for Vite-dependent feature tests, SQLite migrations, PHP syntax parsing, Pint, `composer test`, and `composer audit --locked`.
- Static-analysis job: `composer analyse` using PHPStan 2/Larastan 3, configured at level 5 for `app` with the reviewed `phpstan-baseline.neon`.
- MySQL job: MySQL 8.0 migrations plus database-sensitive regression tests.
- Frontend job: Composer install for Filament theme assets, `npm ci`, `npm run build`, and `npm audit --audit-level=high`.
- CI jobs run independently where possible; the PHP and MySQL jobs build frontend assets because feature tests may render views requiring Vite's manifest.

## Local validation
Run the verified workflow-equivalent checks, adjusting only for unavailable CI services:

1. `composer validate --no-check-publish`
2. `composer install --no-interaction --prefer-dist --no-progress` when dependencies are not already valid.
3. `npm ci` and `npm run build`.
4. `php artisan migrate --force` against a fresh SQLite test database.
5. PHP syntax check over `app`, `bootstrap`, `config`, `database`, `routes`, `scripts`, and `tests` using the workflow's `token_get_all(..., TOKEN_PARSE)` check.
6. `vendor/bin/pint --test tests`
7. `composer test`
8. `composer analyse`
9. `composer audit --locked`
10. `npm audit --audit-level=high`
11. `php scripts/check-documented-composer-scripts.php`
12. Run the MySQL 8.0 migration/integrity regression suite when changing database-sensitive behavior.

## Security and migration status
- Issue #525 resolved the locked Composer security advisories on the Laravel 12 line before the separate Laravel 13 migration in issue #526.
- Do not suppress or ignore known advisories to make CI pass. Re-run the locked audit after updating dependencies.
- Issue #526 contains the Laravel 13 major-version migration; keep it separate from issue #525, which was limited to the Laravel 12 dependency security remediation.
- Production is documented as MySQL 8.x; verify the exact deployed minor version against staging before release.

## Composer scripts and analysis
- Current Composer scripts include `setup`, `dev`, `test`, and `analyse`.
- `composer test` and `composer analyse` are quality gates.
- `lint:check`, `types:check`, and `ci:check` are not defined and must not be called.
- The PHPStan baseline is reviewed technical debt. Remove resolved baseline entries when fixing findings; do not add suppressions without justification and review.
