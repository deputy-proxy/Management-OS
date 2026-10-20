# Baseline test report

## Scope and source revision

- Roadmap issue: [#302 - 1.1 Run and record baseline test suite](https://github.com/deputy-proxy/Management-OS/issues/302)
- Baseline commit: `e7e86a960d640ac74d6f6d6959c49c0f41741198`
- Recorded: 2026-10-21
- Environment: isolated persistent Railway development Sandbox; no production database, credentials, or services used.

## Runtime and setup

| Tool | Observed version | CI workflow version/config |
| --- | --- | --- |
| PHP CLI | 8.5.4 | 8.4 |
| Composer | 2.10.3 | Composer v2 |
| Node.js | v24.21.0 | 22 |
| npm | 11.19.0 | npm lockfile via `npm ci` |
| MySQL | 8.0 Docker image | MySQL 8.0 |

The initial Sandbox lacked PHP extensions required by the committed lockfile and CI: curl, DOM/XML, intl, zip, bcmath, GD, mbstring, and SQLite support. These were installed in the Sandbox only. Composer dependencies were then installed from `composer.lock`, frontend dependencies from `package-lock.json`, and the Vite build completed. This was an environment setup issue, not a repository code failure. The run used PHP 8.5 and Node 24 rather than the workflow's PHP 8.4 and Node 22; that runtime difference is a limitation of this baseline and should be normalized for a strict CI-equivalent rerun.

## Results

All commands below were run against the baseline commit, using a fresh local SQLite database for the SQLite migration and test run.

| Check | Command / method | Result |
| --- | --- | --- |
| Composer manifest | `composer validate --no-check-publish` | PASS; two existing warnings for wildcard constraints on the local path packages `alizharb/filament-activity-log` and `hexters/hexa-lite` |
| PHP dependency setup | `composer install --no-interaction --prefer-dist --no-progress` | PASS after installing the missing PHP extensions |
| Frontend dependency setup | `npm ci` | PASS |
| Frontend build | `npm run build` | PASS; completed in 3.87 seconds |
| SQLite migrations | `php artisan migrate --force` with CI SQLite environment | PASS; all migrations completed |
| PHP syntax | CI `token_get_all(..., TOKEN_PARSE)` check across `app`, `bootstrap`, `config`, `database`, `routes`, `scripts`, and `tests` | PASS |
| PHP formatting | `vendor/bin/pint --test tests` | PASS; 55 files checked |
| PHP test suite | `composer test` | PASS; 261 tests, 1,446 assertions, 36.74 seconds |
| Static analysis | `composer analyse` | PASS; PHPStan/Larastan reported no errors across 779 analysed files |
| Composer dependency audit | `composer audit --locked` | PASS; no security vulnerability advisories |
| Documented Composer scripts | `php scripts/check-documented-composer-scripts.php` | PASS |
| npm dependency audit | `npm audit --audit-level=high` | PASS; 0 vulnerabilities |
| MySQL migrations | `php artisan migrate --force` against a fresh isolated MySQL 8.0 database | PASS; all migrations completed |
| MySQL-sensitive regressions | `php artisan test tests/Feature/PublicFormsTest.php tests/Feature/DatabaseIntegrityAuditTest.php tests/Feature/Filament/ProductReviewResourceAuthorizationTest.php` | PASS; 28 tests, 105 assertions, 64.75 seconds |

The build generated tracked Filament assets during dependency setup. Those generated changes were restored after validation; the report is the only intended repository change.

## Findings and follow-up

- No product/test failures were observed in the checks run.
- No flaky tests were observed in this single run. One run is not sufficient to establish that tests are never flaky.
- No baseline failures require separate product issues based on this run.
- The Sandbox required additional PHP extensions before Composer could install dependencies; the required extensions match the CI workflow.
- For strict comparability, rerun the baseline under PHP 8.4 and Node 22, the versions specified by CI. The observed PHP 8.5/Node 24 results are evidence for this commit, but do not replace the workflow's independent GitHub Actions gate.
