# Payroll period review, approval, close, and correction

## State machine

New payslips start `status=Open` and `approval_status=draft`. The approval state progresses only through:

`draft -> in_review -> approved -> closed`

The legacy `status` changes from `Open` to `Closed` only during the final close transition. Existing historical `Closed` and `Paid` records are labeled `legacy_closed` and `legacy_paid` in the new approval field; this preserves their status without fabricating review or approval evidence.

A period can be submitted only when its latest calculation run is successfully calculated for the same employee and complete calendar month, and there are no unresolved blocking payroll exceptions. A ready, blocked, or schema-only run is not sufficient.

## Independent review and approval snapshots

The review snapshot includes the payslip baseline, legacy JSON breakdown, due date, source-linked items and their immutable calculation snapshots, calculation run IDs/versions and initiating actors, selected payroll input versions, policy versions/configuration, result snapshots and result hashes. It is serialized deterministically and hashed with SHA-256.

The reviewer must be different from the calculation initiator. Approval must be performed by a different actor from both the initiator and reviewer. Approval re-reads the period under a database row lock, rechecks blocking exceptions, rebuilds the snapshot, and rejects any drift from the reviewed hash. The approved snapshot and hash are stored separately and cannot be changed. Reviewer, approver, closer, timestamps, lifecycle version, notes, and snapshot hash are retained in the payslip and append-only `payroll_period_lifecycle_events` history.

Close again acquires a row lock, checks that the period remains approved and open, rechecks blocking exceptions and verifies that the current snapshot still matches the approved hash. Duplicate or stale submissions, approvals, and closes fail rather than recording duplicate transitions. Calculation-run creation also locks the payslip and is rejected once it leaves draft/open. Payroll-period inputs are similarly blocked once review begins.

## Corrections and reversals

Never edit or delete a source line or approved snapshot to repair a closed period. For a draft/open period, an adjustment can remain on that same payslip. Once the period is closed, use the `Record Adjustment` or `Reverse Original Item` actions on a later open payslip for the same employee:

- **Correction:** reference the original item, enter a signed amount, and provide a reason. Positive amounts correct underpayments; negative amounts correct overpayments.
- **Reversal:** reference the original item and provide a reason. The service creates an adjustment equal to the exact opposite of the original item amount.

Both operations require `payroll_period.correct`, preserve the original item and closed snapshot, and append an immutable event containing the original item, target payslip, adjustment item, actor, reason, and signed amount. The unique source key makes a repeated correction against the same item and target period idempotent.

## Permissions and payment boundary

The app-side permissions are `payroll_period.review`, `payroll_period.approve`, `payroll_period.close`, and `payroll_period.correct`. Filament action visibility is backed by the same checks in the lifecycle service and adjustment service. Production role mapping, named business approvers, and operational sign-off remain deferred.

New transitions to `Paid` are disabled until verified payment evidence is integrated. A payment date by itself is not evidence. Historical Paid records remain readable and locked; this workflow does not create payment allocations or claim that a closed period was paid.

The calculation snapshot contains only explicitly configured arithmetic. It does not assert statutory correctness or replace payroll/accounting review. Specialist-approved statutory rules remain a separate requirement.

## Retention and validation

The lifecycle migration refuses rollback once lifecycle events or review/approval snapshots exist. Actor foreign keys restrict deletion so audit attribution is retained. Synthetic regression coverage verifies permissions, distinct actors, duplicate/stale transitions, snapshot drift, closed-period edit rejection, corrections, reversals, transactional rollback, and migration retention. MySQL CI remains the database-specific migration and concurrency regression gate.
