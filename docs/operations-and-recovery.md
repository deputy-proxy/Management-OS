# Production operations, observability, and recovery

## Verified state and unresolved production facts

This repository is a Laravel 12 application. The checked-in configuration supports SQLite, MySQL/MariaDB, PostgreSQL and SQL Server; `.env.example` selects SQLite and the database queue. That example is a development default, not evidence of the production deployment. No deployment manifest, container definition, provider configuration, production environment, backup job, or alerting integration is checked in. The following must be confirmed by the system owner before production approval:

| Component | Verified repository state | Production value / owner |
| --- | --- | --- |
| Hosting and deployment | No provider-specific deployment config found | **Unknown; owner to confirm** |
| Database engine, version and managed-backup feature | Laravel supports several engines; example uses SQLite | **Unknown; owner to confirm** |
| Persistent file/object storage | Local disk is configured by default; no backup target is defined | **Unknown; owner to confirm** |
| Queue driver and worker supervisor | Database queue is the default; production value unknown | **Unknown; owner to confirm** |
| Scheduler runner | Laravel scheduler declares monthly payslip generation; no host cron/scheduler config found | **Unknown; owner to confirm** |
| Mail and external integrations | Example uses log mailer; production integrations not established | **Unknown; owner to confirm** |
| Monitoring, paging, incident owner | No vendor or escalation configuration found | **Unknown; owner to confirm** |
| Backup retention, encryption, RPO/RTO | No enforceable policy or backup job found | **Pending owner approval** |

Do not describe a deployment, backup, restore drill, or alert as verified until evidence from the actual production-like environment is recorded here or in the operational change record. No production access or backup artifact was available during repository implementation, so a production restore drill has **not** been performed by this change.

## Health endpoints

- `GET /up` is Laravel's configured framework health endpoint. Use it as a basic liveness signal: the application process can respond.
- `GET /ready` checks a trivial query against the configured default database connection. It returns only `{"status":"ready"}` with HTTP 200 or `{"status":"not_ready"}` with HTTP 503. It does not return connection names, exception messages, credentials, or topology. Use it as a readiness/dependency signal.
- Neither endpoint proves queue workers or the scheduler are alive. Monitor those separately as described below. Keep detailed health and infrastructure dashboards behind operator authentication; do not add environment/configuration dumps to public endpoints.

Every HTTP response includes `X-Request-ID`. A syntactically valid incoming value of up to 100 ASCII letters, digits, dots, underscores, colons or hyphens is preserved; invalid values are replaced with a generated UUID. The ID is added to Laravel log context. Do not log request bodies, authorization headers, cookies, tokens, passwords, personal records, assessment answers, or arbitrary model JSON. For JSON logs, set `LOG_CHANNEL=stderr_json` and `LOG_LEVEL=info` (or a stricter operationally appropriate level) only after confirming the host collects stderr and retains it securely. The default remains configurable; the existence of a JSON channel alone does not prove logs are exported or alerts are configured.

## Queue operations

Confirm the production queue driver, worker command, supervisor, concurrency, timeout, and `retry_after` relationship with the deployment owner. For the database queue, the repository default `retry_after` is 90 seconds; configure the worker timeout below that value and review it against actual job duration before production use. Do not copy these values blindly to Redis/SQS or long-running jobs.

Useful commands, run from the deployed release with its approved environment:

```sh
php artisan queue:failed
php artisan queue:monitor default --max=100
php artisan queue:retry <failed-job-uuid>
php artisan queue:forget <failed-job-uuid>
php artisan queue:restart
```

`queue:monitor` must be scheduled/triggered by the actual monitoring setup to be useful. Alert on any unexpected failed job for critical workflows and on queue depth/oldest-job age exceeding an owner-approved threshold. Inspect a failed job using restricted operator access before retrying it; retries can repeat external side effects unless that job is idempotent. Use `queue:retry` only after fixing the cause and checking whether the operation already succeeded externally. Use `queue:forget` only after documenting an approved decision to discard it. Never paste raw failed-job payloads or exception dumps into tickets or public chat; they can contain personal data or integration details. Queue driver `sync` does not provide an asynchronous worker or failed-job queue, so production configuration must be verified.

## Scheduler and payslip recovery

The scheduler registers `employee-payslips:generate` for day 1 at 00:05 in `Europe/Bucharest`, with `withoutOverlapping()`. The deployed host still needs a scheduler runner, typically invoking `php artisan schedule:run` every minute, or an equivalent provider-managed scheduler. Confirm that it is actually installed and that the cache/lock backend supports the chosen deployment topology. `withoutOverlapping()` relies on cache locks and can leave a stale lock after an interrupted run; investigate before clearing scheduler locks.

Inspect the schedule from the deployed release:

```sh
php artisan schedule:list
php artisan schedule:run
```

Run `schedule:run` manually only with the normal scheduler's authorization and change controls; it can execute any due scheduled task. Alert if the scheduler runner stops invoking the application or if the monthly generation has not completed shortly after its expected window. There is no verified scheduler heartbeat or external alert integration in the repository yet, so this alert must be implemented by the production operator/monitoring platform.

Payslip generation is designed to be idempotent and supports explicit month recovery. Before backfill, confirm the month, current payslip counts, existing deleted payslips, and source items with payroll staff:

```sh
php artisan employee-payslips:generate --month=YYYY-MM
```

Replace `YYYY-MM` with the approved calendar month. After execution, verify the command exit status, employee/month payslip count, linked source IDs, saved snapshots and totals. Do not delete/recreate closed or paid payslips to force a rerun; preserve financial history and follow `docs/employee-payslip-workflow.md` for adjustments. Record the operator, period, reason, result and reconciliation approval in the restricted operational record.

## Backup policy and restore drill

Before launch, the system owner must approve numeric targets for recovery point objective (RPO), recovery time objective (RTO), backup frequency, retention, encryption/key ownership, storage region, access roles, deletion protection and restore-test cadence. A proposed starting point for approval, not a statement of current policy, is daily encrypted database backups, a 35-day retention window, a monthly isolated restore drill and a documented RPO/RTO. Adjust these targets to the business and legal requirements before accepting them.

Back up the database and every persistent upload/object store needed to interpret the records. Keep backups outside the failure domain of the primary database, restrict access to named operators, encrypt in transit and at rest, and test key recovery. Do not assume local `storage/`, application logs, or a provider's default backup feature is durable or restorable. Keep the encryption keys and restore instructions available to authorized recovery operators without storing secrets in this repository.

The repository provides an artifact sanity check:

```sh
php artisan ops:verify-backup /restricted/path/to/backup-artifact
```

It fails for missing, unreadable or empty artifacts and prints a SHA-256 digest for comparison with a separately stored manifest. It does **not** validate database format, authenticity, completeness, encryption, or restoreability. Do not treat a digest computed from the same untrusted artifact as proof of provenance. Never point a restore command at production during a drill.

### Required isolated restore drill record

1. Create a time-stamped drill record and identify the approved backup, expected point-in-time, operator, reviewer and target RPO/RTO.
2. Verify artifact availability and compare its digest to the trusted backup manifest. Confirm the decryption key is available to the approved operator.
3. Restore into a new isolated database/storage namespace with production network access and outbound integrations disabled. Never overwrite production or send test mail/webhooks.
4. Apply only the documented restore procedure. Record start/end times, errors and actual recovery duration.
5. Run `php artisan migrate:status`, `php artisan database:integrity-audit`, and application smoke tests against the restored target. Reconcile expected record counts and confirm critical records and associated uploaded objects are present. Do not run destructive fixes against the restored copy as part of verification.
6. Record pass/fail, recovered timestamp, measured data loss, measured RTO, discrepancies and corrective actions. Obtain reviewer sign-off.
7. Securely remove the isolated restored data under the approved retention procedure and verify the cleanup.

**Drill status: not performed by this repository change.** Production access, backup configuration and an isolated restore target must be supplied by the system owner. Record the completed drill and evidence before marking the backup acceptance criterion complete.

## Deployment and migration procedure

1. Confirm the release SHA, reviewed PR, green required CI checks, environment configuration, secrets availability and a fresh verified restorable backup.
2. Review migrations for locks, generated-column/index support, foreign-key behavior, table size and data preflight. The current CI migration gate uses SQLite and does not prove production MySQL/InnoDB or other-engine behavior. Test risky migrations against a staging clone running the production engine/version.
3. Deploy the immutable release using the actual hosting provider's approved process. Do not run migrations concurrently from multiple replicas. Run `php artisan migrate --force` once under a single authorized deploy step after the backup and preflight are verified.
4. Rebuild/refresh Laravel config, route and view caches using the platform's established release procedure; restart long-lived queue workers with `php artisan queue:restart` after deploy. Confirm worker processes actually restart.
5. Check `/up`, `/ready`, representative authenticated workflows, queue processing, scheduler execution and error rates. Observe the release before closing the deployment.

Rollback is not universally a code rollback. A migration may be irreversible or its `down()` method may intentionally refuse to remove historical data/constraints. Inspect the specific migration before acting. Prefer a forward fix when the new schema is compatible with the old release. If data changes have occurred, restore or reverse them only under an approved recovery plan with a verified backup and explicit data-loss decision. Never run `migrate:rollback` blindly in production.

## Incident response and alerting baseline

- **Application/HTTP:** alert on sustained 5xx rate, elevated latency, failed `/up` or `/ready`, and repeated errors sharing a request ID. Set thresholds from observed baseline and business hours; do not invent them from development traffic.
- **Database:** alert on failed readiness, connection exhaustion, storage/capacity thresholds, replication/backup failures where applicable, and integrity-audit findings. A healthy `/ready` does not prove backups or integrity are healthy.
- **Queue:** alert on failed critical jobs, growing queue depth/age, worker process absence, and repeated retries. Restrict failed-job payload access.
- **Scheduler:** monitor the host's scheduler invocation and monthly payslip generation completion; reconcile expected payslips after the monthly run.
- **Integrations/mail:** use provider delivery/error metrics and synthetic checks that do not expose real customer data. The application currently has no provider-specific integration alerting configured.
- **Security/privacy:** send structured logs to a restricted, access-controlled sink with retention approved by the owner. Do not include secrets, request bodies, credentials or raw personal records in alerts. Share request IDs and sanitized error categories instead.

On incident: assign an incident lead, record UTC/local timestamps and impact, preserve relevant restricted logs, stop unsafe retries or deployments, identify the last known-good release/backup, and follow the approved recovery path. After recovery, validate key workflows and reconcile financial/academic history before declaring resolution. Record cause, impact, corrective actions and owners. Hosting provider, incident owner, paging destination, and exact thresholds remain **unknown and require explicit assignment**.
