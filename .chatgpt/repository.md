# Repository-specific development configuration

Last verified: 2026-11-20. Exact resolved versions are recorded in the cross-repository matrix at [Cr8or](https://github.com/deputy-proxy/cr8or/blob/main/.chatgpt/repository-versions.md). Re-check manifests, lockfiles, and workflows before each implementation.

## Runtime and resolved framework versions
- PHP constraint: `^8.4.1`; CI uses PHP 8.4.
- Laravel 13.43.0; Filament 5.11.4; Livewire 4.4.8.
- PHPUnit 12.5.45; Larastan 3.15.0; Pint 1.32.3.
- Node.js 22; npm lockfile resolves Vite 8.3.8, Tailwind CSS 4.3.5, Laravel Vite plugin 3.2.1.
- Composer manifest now requires Laravel `^13.0`; the previous Laravel 12 migration has been reflected in the current manifest and lockfile. Verify the actual default branch before assuming older issue descriptions remain current.

## Current CI validation
Source: `.github/workflows/tests.yml`.
- PHP job: Composer validation, documented-script verification, install, environment setup, frontend build for Vite-dependent feature tests, SQLite migrations, PHP syntax parsing, Pint, `composer test`, and `composer audit --locked`.
- Static analysis: `composer analyse` using PHPStan 2/Larastan 3 and the reviewed baseline.
- MySQL job: MySQL 8.0 migrations plus database-sensitive regression tests.
- Frontend job: Composer install for Filament theme assets, `npm ci`, `npm run build`, and `npm audit --audit-level=high`.

## Local validation
Follow the actual current workflow and Composer scripts. At minimum, validate Composer configuration, npm build, SQLite migrations, PHP syntax, Pint, tests, PHPStan, Composer audit, npm audit, and documented Composer scripts. Run MySQL 8.0 regression tests for database-sensitive changes.

## Security and migration notes
- Re-run `composer audit --locked` against the current lockfile. Earlier audit findings were against a previous Laravel 12 dependency tree and must not be presented as current findings without reproducing them.
- Verify both SQLite and MySQL-specific tests when changing database behavior.
- Review and reduce the PHPStan baseline when fixing findings; do not add suppressions without justification.


## MCP implementation state
- Streamable HTTP transport, Passport authorization-code + PKCE, resource/audience binding, dedicated AI principal, and server-side tool authorization are implemented. The capability registry contains six read-only executive tools, nine read-only finance tools and seven read-only CRM/sales tools from #535. The versioned permission matrix gates each tool with dedicated OAuth scopes, required/optional HexaLite permissions, record scopes and output field allowlists. Operations tools and business mutations remain disabled until their individual issues are implemented and tested. CRM tool schemas and privacy boundaries are documented in docs/mcp-crm-tools.md.
- Issue #532 adds `mcp_approvals` and `mcp_execution_audits`, encrypted operation arguments/previews/results, payload-bound independent human review, single-use execution claims, idempotency, expiry, and stale-execution recovery.
- Human-only Filament resources: MCP Approvals (`mcp_approval.index`, `mcp_approval.approve`) and MCP Execution Audit (`mcp_execution_audit.index`). The AI principal cannot review its own operations.
- `mcp:expire-approvals` is scheduled every minute. No MCP-specific audit retention period has been approved; no MCP audit deletion is scheduled.
- See `docs/mcp-server.md`, `docs/mcp-permissions.md`, and `docs/mcp-approvals-and-audit.md`.
