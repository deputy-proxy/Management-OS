# Resilience and recovery test guide

This guide separates repeatable application tests from infrastructure drills that require an authorized operator. CI uses synthetic fixtures and isolated test databases only.

## Local application test suite

Prepare dependencies and a local testing environment:

```sh
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate --force
```

Run the focused resilience and data-integrity regression set:

```sh
php artisan test tests/Feature/Operations/HealthAndObservabilityTest.php
php artisan test tests/Feature/BankStatementImportBatchServiceTest.php
php artisan test tests/Feature/GoogleAdsCampaignMetricsImporterTest.php
```

The tests use SQLite/in-memory databases, array cache, synchronous queues, fake provider HTTP responses, synthetic records, and temporary non-sensitive backup fixtures. They do not connect to production services.

Coverage includes:

- Database readiness failures return a minimal unavailable response and do not disclose exception details.
- Queue-worker and scheduler heartbeat checks fail closed for missing, stale, future, malformed, or unavailable dependency state.
- The scheduler heartbeat callback records a timestamp that the health command can consume.
- Failed-job monitoring reports only counts/status, fails when the failed-jobs table is unavailable, and never prints payloads or exception contents.
- Backup artifact checks reject missing/empty artifacts and compare an optional digest against a separately trusted manifest. They explicitly do not claim to prove restoreability.
- Bank statement import retries are audited; duplicate source rows require review; partial item persistence rolls back atomically when final validation fails.
- Google Ads import tests cover idempotent replays, provider errors, malformed/duplicate rows, out-of-range dates, currency mismatches, and preservation of prior metric revisions when a later batch fails.

Run the complete CI-equivalent checks before opening a pull request:

```sh
composer test
composer analyse
composer audit --locked
```

The repository CI additionally runs MySQL migration and database-sensitive regression tests, PHP syntax/format checks, and the frontend build/audit. SQLite results do not prove MySQL production behavior.

## Failure-injection boundaries

Application tests may simulate dependency exceptions, malformed provider responses, stale heartbeats, missing operational tables, invalid artifacts, and transaction failures using mocks, fakes, and isolated test databases.

Do not use these tests as permission to:

- terminate production database connections or workers;
- retry, forget, or delete live failed jobs;
- invoke due production scheduled tasks manually;
- change provider alert routing or hosting configuration;
- restore over production or use real customer/financial data in fixtures.

## Operator-only restore and alert validation

The following remain outside automated CI and require an authorized operator plus an isolated restore target:

1. Verify the selected backup against a separately protected manifest.
2. Restore database and persistent files into a new isolated namespace with production network access and outbound integrations disabled.
3. Record restore start/end times and the recovered timestamp; calculate measured RPO and RTO.
4. Run `php artisan migrate:status`, `php artisan database:integrity-audit`, and approved application smoke tests against the restored copy.
5. Reconcile critical financial/invoice records, payroll snapshots and totals, relationships, and required file references without destructive auto-fixes.
6. Verify alert delivery to the approved recipient, record reviewer sign-off, and verify secure cleanup of the isolated copy.

The owner-approved targets documented in [recovery-and-availability-objectives.md](recovery-and-availability-objectives.md) are policy targets, not measured results. Do not close the production-readiness gap based on passing application tests or a successful artifact hash check alone. Store drill evidence in the restricted operational record, not in this repository.
