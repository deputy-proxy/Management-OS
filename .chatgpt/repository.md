# Repository-specific development configuration

Last verified: 2026-11-18. Re-check the live workflows and manifests before every implementation; this file is a snapshot, not a substitute for inspection.

## Runtime and framework
- Composer PHP requirement: `^8.2`; CI currently runs PHP 8.4.
- Laravel: 12.x. Issue #526 tracks migration to Laravel 13.
- Filament: 5.x.
- Livewire: 4.x (resolved dependency; verify the lockfile if the manifest changes).
- Node.js: 22.
- Frontend package manager: npm, using committed `package-lock.json`.
- SQLite CI database for the main PHP job.
- MySQL 8.0 for database migration and integrity regression tests.
- Java 21 for pinned CIUS-RO Schematron validation.

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
- The Composer audit has reported advisories in the locked dependency tree, including a Laravel 12 security advisory and Symfony advisories. Issue #525 tracks immediate remediation on the Laravel 12 line.
- Do not suppress or ignore known advisories to make CI pass. Re-run the locked audit after updating dependencies.
- Laravel 13 migration is tracked separately in issue #526. Do not combine the security remediation and major-version migration unless the issue plan explicitly requires it.
- Production is documented as MySQL 8.x; verify the exact deployed minor version against staging before release.

## Composer scripts and analysis
- Current Composer scripts include `setup`, `dev`, `test`, and `analyse`.
- `composer test` and `composer analyse` are quality gates.
- `lint:check`, `types:check`, and `ci:check` are not defined and must not be called.
- The PHPStan baseline is reviewed technical debt. Remove resolved baseline entries when fixing findings; do not add suppressions without justification and review.
