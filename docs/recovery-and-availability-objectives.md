# Recovery and availability objectives

**Status:** owner-approved baseline targets with verification still pending.
**Last repository review:** 2026-10-25.
**Scope:** application availability, database and persistent-file recovery, critical financial/academic records, and operational response. This document defines targets and test evidence; it does not certify the live hosting configuration or a successful restore.

## Approved baseline and evidence status

| Objective | Current target / policy | Evidence status | Verification required |
| --- | --- | --- | --- |
| Recovery point objective (RPO) | No more than 1 day of data loss | Owner-approved target, not independently demonstrated | Confirm backup cadence, successful completion times, database and persistent-file consistency, and the latest recoverable timestamp from a witnessed isolated restore. |
| Recovery time objective (RTO) | Restore critical service within 4 hours | Owner-approved target, not independently demonstrated | Time an isolated restore from incident declaration through database/files restore, application validation, and authorized service-ready decision. Record actual duration and exclusions. |
| Backup cadence and retention | Daily backups, 14-day retention | Owner-reported policy; provider settings unverified | Capture provider configuration and representative successful/failed backup evidence in restricted operational storage. |
| Restore-drill cadence | Monthly isolated restore drill | Owner-reported policy; current complete evidence record unavailable | Record each drill using the evidence checklist below. |
| Application availability | No numeric availability SLO approved | Not set | Collect real production request/uptime baseline, agree measurement window and exclusions, then approve an SLO. Do not invent a percentage from development data. |
| Critical records | Preserve financial transactions, invoices, payslips/payroll source links and snapshots, employee records, and required uploaded files; no silent loss or unreviewed financial-history rewrite | Scope inferred from application workflows; restore integrity not fully demonstrated | Build a versioned restore validation checklist and reconcile representative record counts, relationships, totals and required file references in an isolated environment. Obtain domain-owner sign-off. |
| Dependencies | Database, persistent files, cache-backed scheduler/worker coordination, queue processing, mail/integration delivery | Mixed: some repository checks exist; external configuration and end-to-end delivery unverified | Validate each dependency independently and after restore; confirm cache persistence/locks, worker/scheduler activity, and alert delivery without sending real customer data. |
| Escalation | System administrator is the reported primary owner; backup incident contact not designated | Partial / gap | Record primary contact route in restricted operational storage and nominate a backup contact before treating escalation as covered. |

RPO and RTO are business objectives, not measurements. The runbook records an owner-reported isolated restore on 2026-10-18 taking about 30 minutes, but the full evidence record was not available to this audit. That report alone does not establish that the four-hour RTO or one-day RPO is met.

## Critical workflow tiers

| Tier | Workloads | Recovery expectation |
| --- | --- | --- |
| Tier 1: data integrity | Database, financial transaction/invoice relationships, payslip source links and snapshots, employee/academic records and persistent files required to interpret them | Restore a consistent database/files point; reconcile critical records and totals before declaring recovery. Never silently rewrite historical records to make counts match. |
| Tier 2: service operation | Authenticated application, database readiness, queue worker, scheduler and cache/locks | Restore the application and dependencies within the approved four-hour target, subject to a measured drill. A successful `/up` alone is insufficient. |
| Tier 3: communications and monitoring | Mail/integration delivery, request-correlated logs, heartbeat and failed-job monitoring | Restore and verify delivery paths; identify undelivered work and reconcile before retrying operations that may have external side effects. |

These tiers are an operational proposal based on existing workflows. A business owner must approve the criticality and any acceptable data-loss exceptions before the tiers are treated as formally approved policy.

## Safe verification procedure

All restore and failure-injection work must use a new isolated database and file-storage namespace. Disable production network access and outbound mail/webhooks/integrations. Do not test by overwriting production, deleting production data, retrying live jobs, or changing hosting configuration without a separate approved change plan.

1. **Approval and preparation**
   - Record the drill ID, approver, operator, independent reviewer, planned backup identifier and target point-in-time.
   - Confirm isolation, disabled outbound integrations, restricted access, cleanup owner and cleanup deadline.
   - Verify backup artifact availability and compare its digest against a separately protected trusted manifest. The repository's backup artifact check alone does not prove provenance or restoreability.
2. **Measure the recovery point**
   - Record the backup's source timestamp and the latest recoverable database/file timestamps.
   - Compare those timestamps with the incident/drill reference point to calculate observed data loss. Record RPO pass/fail against 24 hours.
   - Verify that the database and persistent files correspond to a consistent point; do not assume that independently copied artifacts are consistent.
3. **Restore and measure time**
   - Start the timer at the agreed incident declaration/start point.
   - Restore only into the isolated target using the approved provider-specific procedure.
   - Record command outcomes and start/end timestamps. Do not put secrets, connection strings, backup contents or personal data in this repository.
4. **Validate service and data**
   - Run `php artisan migrate:status`, `php artisan database:integrity-audit`, and application smoke tests against the restored target.
   - Validate `/up` and `/ready`, representative authenticated flows, queue/scheduler startup, required file access, and mail/integration behavior using sinks or mocks.
   - Reconcile critical record counts, relationships, payslip snapshots/totals, and uploaded-file references with the selected backup point. Record discrepancies and do not run destructive auto-fixes.
5. **Decide and close**
   - Stop the timer only when the approved critical-service validation criteria pass and the reviewer agrees the service is recoverable. Record RTO pass/fail against four hours.
   - Attach sanitized command output and result summaries in restricted operational storage. Record reviewer sign-off, corrective actions, owners and due dates.
   - Securely delete the isolated restored data using the approved cleanup procedure and verify cleanup. Record completion without including sensitive artifacts here.

## Incident response and recovery sequence

1. Declare the incident, identify the incident lead, record the start time and affected workflows, and stop unsafe deployments/retries.
2. Assess `/up`, `/ready`, database availability, worker/scheduler heartbeat and failed-job count. Preserve restricted logs with request IDs; never expose raw failed-job payloads in tickets.
3. Identify the last known-good release and backup. Confirm backup availability and the expected recovery point before destructive action.
4. Restore to an isolated target first when database or persistent-file integrity is uncertain. Production restoration requires a separately approved recovery plan and explicit data-loss decision.
5. Validate critical data and files, then application readiness, queue/scheduler operation and controlled integration delivery. Reconcile financial and academic history before declaring resolution.
6. Record recovery start/end, observed RPO/RTO, unresolved data discrepancies, approval, corrective actions and a post-incident review.

Do not blindly run `migrate:rollback`, clear scheduler locks without investigation, bulk-retry failed jobs, or recreate closed/paid payslips. Retries can repeat external side effects; preserve history and reconcile first.

## Ownership and outstanding approvals

- **System owner:** approve service availability SLO, critical workflow tiers, data-loss exceptions, escalation windows, backup risk disposition and operational contacts.
- **Hosting administrator:** verify actual backup provider settings, coverage/retention, runtime/database version, deployment process, worker/scheduler settings, storage capacity and restore procedure.
- **Application operator:** perform approved checks, maintain evidence, inspect failed jobs under restricted access, and coordinate reconciliation with financial/academic domain owners.
- **Independent reviewer:** review each restore drill and evidence record; identity/assignment still needs to be designated.

No numeric availability SLO or alert thresholds are invented here. The availability objective remains unapproved until a production baseline and business-owner decision exist. Backup encryption and separate deletion/immutability protection are recorded as known gaps in the operations runbook and require explicit remediation or risk acceptance.
