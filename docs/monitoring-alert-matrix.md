# Monitoring and alert verification matrix

**Status:** implementation and verification plan; external monitor configuration and alert receipt remain unverified.
**Last reviewed:** 2026-10-25.

This matrix connects repository-backed probes to operational actions. It is not evidence that a provider monitor is configured or that an alert was delivered. Record provider-specific configuration, threshold approvals, contact routes, and sanitized test receipts in the restricted operational evidence store, never in this repository.

## Alert coverage

| Failure mode | Probe / signal | Trigger behavior | Actionable response | Current evidence / gap |
| --- | --- | --- | --- | --- |
| Application process unavailable | `GET /up` | HTTP check fails or sustained 5xx/latency threshold breached | Check release health, application logs by request ID, and hosting status; follow deployment/incident runbook | Endpoint exists; external check and thresholds unverified |
| Database unavailable | `GET /ready`; `php artisan ops:check-heartbeats` | Readiness returns 503 or command exits non-zero | Check database reachability/capacity and recent deploy/migration; do not expose connection details in alert | Endpoint and command are tested in CI; external alert receipt unverified |
| Queue worker or scheduler stopped | `php artisan ops:check-heartbeats --max-age=180` | Missing/stale heartbeat produces non-zero exit | Check host cron/worker invocation, sanitized command output, shared cache and process logs | Command tests exist; host invocation and alert delivery unverified |
| Failed asynchronous jobs | `php artisan ops:check-failed-jobs --max-failed=0` | Failed count exceeds approved threshold or failed-jobs table unavailable | Inspect jobs under restricted access; classify cause and external side effects before any selective retry/discard | Command tests exist. Runbook records 78 historical failures from 2026-10-18; current count must be rechecked and not assumed unchanged |
| Queue backlog / oldest job | Laravel `queue:monitor` and provider/queue telemetry | Depth or age exceeds owner-approved threshold | Check worker throughput, oldest job age, repeated retries and capacity; do not bulk-retry | Monitor command exists, but no tracked external schedule/threshold evidence |
| Monthly payslip generation incomplete | Scheduler/job completion signal plus period-specific reconciliation | Expected completion is absent after the approved run window, or reconciliation fails | Verify approved month, expected employee count, source links, snapshots and totals with payroll owner; use the documented recovery command only after review | Schedule exists; actual monthly completion and external alert path remain unverified |
| Backup failed / stale / incomplete | Backup provider status and artifact checks; `php artisan ops:verify-backup <path>` for a known artifact | Provider reports failure/missed cadence, or artifact is missing/unreadable/empty | Confirm provider coverage and trusted manifest, investigate storage/access, and escalate before the RPO is at risk | Local sanity check is tested; provider integration, backup freshness signal and delivery are unverified |
| Storage capacity / required files missing | Hosting disk/object-store metrics plus isolated restore/file checks | Owner-approved capacity threshold or required-file access failure | Check capacity, persistent paths, upload errors and backup coverage; do not delete user files as a quick fix | No provider-specific metric or threshold is tracked |
| Mail / integration delivery failure | Provider delivery/error metrics and a synthetic test using a sink/test recipient | Provider rejection/bounce/error threshold or synthetic check failure | Check provider status and credentials through restricted access; use a test sink and never include real customer data in alerts | Mail provider is owner-reported; receipt of monitoring alerts and integration alert configuration are unverified |

## Alert payload and deduplication rules

- Include the failing probe/workload, environment label, first-seen and latest-seen timestamps, a sanitized error category, runbook link, and a correlation/request ID when available.
- Never include secrets, connection strings, raw request bodies, full failed-job payloads, exception dumps, payroll/financial records, or personal data.
- Group repeated notifications by stable key such as environment + probe + failure category. Update the existing incident while the condition persists; emit a recovery notification after a healthy result. Do not suppress a new failure category merely because another alert for the same service is active.
- Apply a short evaluation window or consecutive-failure rule for noisy transient HTTP/provider signals. Do not delay alerts for sustained database unavailability, failed backup cadence, missing critical scheduled work, or data-integrity failures. Exact windows and thresholds require owner approval based on observed baselines.
- Route to the designated primary operational contact and a named backup contact. A backup contact is currently not designated, and the exact external alert route is not evidenced. Do not mark routing as verified until both configuration and receipt are recorded.
- Every alert must link to the relevant runbook section and identify a safe first action. Destructive recovery, production restore, bulk retries, and deletion require separate authorization and must never be triggered automatically by an alert.

## Safe verification and evidence

1. Record the monitor/provider, environment, check name, approved threshold/window, routing target reference, operator, and test approval in restricted storage.
2. In an approved non-production environment, exercise one failure at a time: failed HTTP/readiness check, missing/stale heartbeat, synthetic failed job, backup-check failure using a disposable fixture, and synthetic provider/mail failure using a sink. Do not inject failures or send test data through production workflows without a separately approved plan.
3. Verify the check's exit/status, alert arrival, actionable runbook link, deduplication during repeated failure, and recovery notification. Capture timestamps and sanitized evidence; redact recipient addresses and provider identifiers from repository artifacts.
4. Confirm a failed check does not disclose sensitive data and that repeated alerts do not hide a distinct failure. Restore the test fixture/state and record cleanup.
5. Record PASS / FAIL / NOT RUN per check, evidence references, corrective action, owner and due date. `NOT RUN` is not a pass. Do not claim the monitoring control is closed until critical alerts reach the assigned owner and the receipt evidence is reviewed.

## Current blockers

- External monitor/provider configuration is not stored in the repository and has not been independently inspected.
- Actual thresholds and evaluation windows are not owner-approved from a production baseline.
- Test alert receipt, deduplication behavior and recovery notifications have not been evidenced.
- No backup incident contact is designated.
- Queue backlog/age, backup failure/freshness, storage capacity, integration delivery and monthly critical-workflow completion need provider-level signal wiring and safe verification.

Use this document as the implementation checklist, not as a readiness certification. See [operations-and-recovery.md](operations-and-recovery.md) for command details and [production-readiness-audit.md](production-readiness-audit.md) for the wider control inventory.
