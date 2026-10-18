# Production operations, observability, and recovery

## Verified state and unresolved production facts

The system owner supplied the following production-readiness answers on 2026-10-18. These are owner-reported statements, not independently verified through the hosting control panel or production telemetry.

| Component | Owner-reported state | Remaining evidence / owner action |
| --- | --- | --- |
| Hosting | Hostinger shared hosting | Confirm the exact plan/product capabilities and whether a continuously running queue worker is supported. |
| Deployment | Git-based deployment | Document the actual release steps, migration execution point, rollback procedure, and responsible operator. |
| Database | MySQL; exact server/version unknown | Record engine/version and capacity monitoring; verify migrations, generated indexes and locking against a staging copy of that version. |
| Persistent files | Local disk on the hosting account | Confirm which paths contain user uploads, how they are included in backups, disk-capacity monitoring, and recovery behavior after redeploy/restore. |
| Queue worker | Owner reports “Laravel Cloud” supervision | Clarify the exact service and how it relates to the Hostinger shared-hosting deployment; record queue driver, worker command, timeout, `retry_after`, restart procedure, and production heartbeat evidence. Do not assume the worker is healthy merely because a service is named. |
| Scheduler | **No scheduler configured** | Install and verify a supported scheduler runner before relying on heartbeat checks or monthly payslip generation. Record the invocation and test missed-run alerting. |
| Mail provider | Unknown | Identify the provider and test delivery/error monitoring without exposing customer data. |
| Cache backend | Unknown | Identify the configured cache store and verify that application, queue worker, scheduler, and monitoring commands share a durable store suitable for heartbeat values and locks. |
| Monitoring and alert delivery | Not configured or unknown; destination unknown | Configure and test alert routing. Owner-selected events are database/readiness failures, queue worker/failed jobs, scheduler heartbeat, and monthly payslip generation. Also decide whether backup failures, storage capacity, and mail/integration failures must alert. |
| Operational ownership | Service owner and incident escalation both reported as “admin” | Identify the accountable person or role, a backup contact, and the actual notification route. Confirm access and availability during an incident. |
| Backup provider and restore | Hostinger; coverage/restore options checked by owner. An isolated non-production restore was reported successful on 2026-10-18. | The restore remains owner-reported, not independently verified. Store the backup identifier, isolated target, database and file checks, command results, start/end times, measured data loss/RPO, measured RTO, reviewer sign-off and cleanup evidence in the restricted operational record. |
| Backup policy | Owner-selected daily frequency and 14-day retention | Confirm these settings match the provider's actual configured backup plan and include the database plus required local uploads. Confirm storage region and whether backups are isolated from the primary failure domain. |
| RPO / RTO | Owner-selected RPO 15 minutes; RTO 4 hours | Daily backups alone do not meet a 15-minute RPO. Verify that point-in-time recovery or another sufficiently frequent recovery mechanism exists and test it. Demonstrate the 4-hour RTO with a timed restore drill. |
| Encryption and key ownership | Unknown | Verify encryption at rest and in transit, key ownership/recovery, and access to keys during a restore. Do not store secrets in this repository. |
| Backup access/deletion protection | Named operators only, needs confirmation | Verify actual role assignments, least privilege, auditability, and deletion/immutability protection with the provider. |

The repository has no production credentials and does not infer undocumented settings. Do not treat owner-reported restore success as a fully evidenced drill until the restricted operational record contains the evidence above. Never put secrets, backup contents, personal data or credential-bearing connection strings in this repository or issue comments.

## Health endpoints

- `GET /up` is Laravel's configured framework health endpoint. Use it as a basic liveness signal: the application process can respond.
- `GET /ready` checks a trivial query against the configured default database connection. It returns only `{"status":"ready"}` with HTTP 200 or `{"status":"not_ready"}` with HTTP 503. It does not return connection names, exception messages, credentials, or topology. Use it as a readiness/dependency signal.
- Neither endpoint proves queue workers or the scheduler are alive. The private `ops:check-heartbeats` Artisan command checks database connectivity and recent queue-worker/scheduler heartbeat timestamps. It is not exposed as a public HTTP endpoint. Keep detailed health and infrastructure dashboards behind operator authentication; do not add environment/configuration dumps to public endpoints.

Every HTTP response includes `X-Request-ID`. A syntactically valid incoming value of up to 100 ASCII letters, digits, dots, underscores, colons or hyphens is preserved; invalid values are replaced with a generated UUID. The ID is added to Laravel log context. Do not log request bodies, authorization headers, cookies, tokens, passwords, personal records, assessment answers, or arbitrary model JSON. For JSON logs, set `LOG_CHANNEL=stderr_json` and `LOG_LEVEL=info` (or a stricter operationally appropriate level) only after confirming the host collects stderr and retains it securely. The default remains configurable; the existence of a JSON channel alone does not prove logs are exported or alerts are configured.

## Queue operations

Confirm the production queue driver, worker command, supervisor, concurrency, timeout, and `retry_after` relationship with the deployment owner. For the database queue, the repository default `retry_after` is 90 seconds; configure the worker timeout below that value and review it against actual job duration before production use. Do not copy these values blindly to Redis/SQS or long-running jobs.

Useful commands, run from the deployed release with its approved environment:

The application records queue-worker heartbeat timestamps while a worker loop runs and records a scheduler heartbeat every minute. The heartbeat values use Laravel's configured cache store, so the worker, scheduler and monitoring invocation must share a durable cache backend. Run the following from the monitoring system or a restricted operator shell:

```sh
php artisan ops:check-heartbeats --max-age=180
php artisan ops:check-failed-jobs --max-failed=0
```

Exit code `0` means the database is reachable and both heartbeat timestamps are recent. Exit code `1` means at least one check failed; configure the external monitor to alert on that result. `ops:check-failed-jobs` returns a non-zero exit if the failed-job count exceeds `--max-failed` (default `0`), reports only the count and threshold, and never prints job payloads or exception text. A missing/unavailable failed-jobs table also fails the check. Set a nonzero threshold only after the owner explicitly accepts that alert policy and has a process to inspect/clear failures safely. The command intentionally reports only healthy/unhealthy labels, not hostnames, credentials or exception messages. It does not replace queue-depth, failed-job, integration, or payslip-completion monitoring.


```sh
php artisan queue:failed
php artisan queue:monitor default --max=100
php artisan queue:retry <failed-job-uuid>
php artisan queue:forget <failed-job-uuid>
php artisan queue:restart
```

`queue:monitor` must be scheduled/triggered by the actual monitoring setup to be useful. Alert on any unexpected failed job for critical workflows and on queue depth/oldest-job age exceeding an owner-approved threshold. The application records a queue-worker heartbeat on queue loop iterations in the configured shared cache. Check it with `php artisan ops:check-heartbeats`; this only proves a worker loop has run recently, not that every queue is draining or every job succeeds. The worker and scheduler must use the same shared cache backend as the command. Use `php artisan ops:check-failed-jobs --max-failed=0` from the external monitor to detect unreviewed failed jobs without leaking payloads. Inspect a failed job using restricted operator access before retrying it; retries can repeat external side effects unless that job is idempotent. Use `queue:retry` only after fixing the cause and checking whether the operation already succeeded externally. Use `queue:forget` only after documenting an approved decision to discard it. Never paste raw failed-job payloads or exception dumps into tickets or public chat; they can contain personal data or integration details. Queue driver `sync` does not provide an asynchronous worker or failed-job queue, so production configuration must be verified.

## Scheduler and payslip recovery

The scheduler registers `employee-payslips:generate` for day 1 at 00:05 in `Europe/Bucharest`, with `withoutOverlapping()`. The system owner reports that no production scheduler is currently configured. This is an operational blocker: install a supported runner that invokes `php artisan schedule:run` every minute, or an equivalent provider-managed scheduler, then verify actual execution and heartbeat recording. Confirm that the cache/lock backend supports the chosen deployment topology. `withoutOverlapping()` relies on cache locks and can leave a stale lock after an interrupted run; investigate before clearing scheduler locks. Until the runner is installed and tested, do not rely on monthly payslip generation or scheduler heartbeat alerts.

Inspect the schedule from the deployed release:

```sh
php artisan schedule:list
php artisan schedule:run
```

Run `schedule:run` manually only with the normal scheduler's authorization and change controls; it can execute any due scheduled task. Alert if the scheduler runner stops invoking the application or if the monthly generation has not completed shortly after its expected window. A named scheduler heartbeat is now recorded every minute by the Laravel schedule. Use `php artisan ops:check-heartbeats` from the external monitoring platform to detect missing/stale scheduler and queue-worker heartbeats. This command does not send alerts itself; configure the production scheduler to invoke the heartbeat event and configure an external monitor to run the check and page the named owner on non-zero exit.

Payslip generation is designed to be idempotent and supports explicit month recovery. Before backfill, confirm the month, current payslip counts, existing deleted payslips, and source items with payroll staff:

```sh
php artisan employee-payslips:generate --month=YYYY-MM
```

Replace `YYYY-MM` with the approved calendar month. After execution, verify the command exit status, employee/month payslip count, linked source IDs, saved snapshots and totals. Do not delete/recreate closed or paid payslips to force a rerun; preserve financial history and follow `docs/employee-payslip-workflow.md` for adjustments. Record the operator, period, reason, result and reconciliation approval in the restricted operational record.

## Backup policy and restore drill

Owner-selected targets supplied on 2026-10-18 are RPO 15 minutes, RTO 4 hours, daily backups, and 14-day retention. These are decisions/targets, not proof that the provider configuration can meet them. **Daily backups alone cannot meet a 15-minute RPO.** Confirm whether Hostinger provides point-in-time recovery or configure another approved mechanism that demonstrably meets the target. If the target cannot be achieved, the owner must explicitly revise the RPO or approve a feasible recovery design. The owner still needs to verify encryption/key ownership, storage region, access roles, deletion protection, and restore-test cadence. A monthly isolated restore drill is a proposed starting point, not an approved cadence until confirmed.

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

**Drill status: system owner reports a successful isolated restore on 2026-10-18.** The repository change did not perform or independently verify that restore. The backup acceptance criterion remains incomplete until a restricted drill record captures the artifact identifier, isolated target, restored database and local-file checks, `migrate:status`, integrity audit and smoke-test results, measured data loss/RPO and RTO, start/end times, reviewer sign-off and secure cleanup. The drill must demonstrate the owner-selected 15-minute RPO and 4-hour RTO; a restore that succeeds but misses either target does not satisfy the policy.

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

On incident: assign an incident lead, record UTC/local timestamps and impact, preserve relevant restricted logs, stop unsafe retries or deployments, identify the last known-good release/backup, and follow the approved recovery path. After recovery, validate key workflows and reconcile financial/academic history before declaring resolution. Record cause, impact, corrective actions and owners. The owner has identified Hostinger shared hosting and “admin” as service owner/incident escalation, but the exact accountable person/role, backup contact, alert destination, tested alert route and thresholds remain unresolved.