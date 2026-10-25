# Payroll schema change readiness and migration plan

**Status: pre-implementation plan only. No schema change is approved by this document.** The payroll input contract (#338), policy approval (#337), worker arrangement evidence (#335), and current requirements review (#336) remain prerequisites. This plan deliberately does not choose columns, defaults, statutory formulas, or data conversions before those decisions are approved.

## 1. Existing persistence constraints

| Existing record | Current contract / safeguard | Migration constraint |
| --- | --- | --- |
| `employee_contracts` | Employee, start/end dates, contract type, legacy integer `base_salary` and `hourly_rate`, JSON clauses, soft deletion. | Do not reinterpret integer units, currency, salary basis or JSON clauses without reconciled evidence and an approved field contract. |
| `employee_pay_slips` | Employee/month unique key; legacy `items` JSON; `manual_amount`; aggregate `amount`; due/paid dates; `Open`, `Closed`, `Paid` lifecycle. | Preserve month identity, legacy JSON and historical values. Paid/closed records must not be silently recalculated or rewritten. |
| `employee_pay_slip_items` | Source-linked activity/adjustment rows with unique source key, decimal amount/quantity/rate, currency and calculation snapshot. Update/delete is prohibited. | Preserve all source IDs and snapshots. Do not backfill synthetic source details into legacy JSON blocks where no authoritative mapping exists. |
| Leave/timesheet sources | Existing employee holiday, medical leave and timesheet records with different shapes and approval/evidence limitations. | Do not merge sources or infer approval, paid status, hours, or legal treatment from the presence of a row alone. |
| Supplier invoices/payments | Vendor, contract, invoice, transaction and payment-allocation records coexist with legacy and decimal amount fields. | Keep invoice/PFA workflows separate from employee payroll. Resolve amount precedence, classification and payment semantics before any schema or behavior change. |

Relevant safeguards are documented in `docs/employee-payslip-workflow.md`, `docs/payroll-input-contract-draft.md`, `docs/payroll-policy-approval-template.md`, and `docs/contractor-invoice-treatment-decision-template.md`.

## 2. Preconditions for proposing a migration

No migration PR should be opened until all applicable gates are satisfied:

- [ ] The real workforce and contract inventory is reconciled from controlled evidence; unresolved worker types are explicitly flagged.
- [ ] HR/Finance approves the input contract, source-of-truth mapping, required fields, validation and schema-gap list.
- [ ] A qualified Romanian payroll/accounting specialist approves statutory policy, effective dates, expected-result fixtures and versioning behavior.
- [ ] Finance approves the separate contractor invoice lifecycle, legacy amount-field precedence, allocation and correction semantics.
- [ ] Data owner/security reviewer approves sensitive-field access, evidence references, audit logging and retention.
- [ ] Each proposed field has a documented consumer, type/precision, requiredness, validation, effective-date semantics, source and migration/backfill rule.
- [ ] A sanitized production-like dataset can be used for rehearsal without exposing actual personal or financial data in Git.

If any prerequisite is missing, stop at documentation or non-mutating diagnostics. Do not add speculative nullable fields that will later be treated as approved schema.

## 3. Staged migration sequence after approval

### Stage A: baseline and export

1. Record the exact base commit and migration list.
2. Export affected records through the approved restricted process; preserve original values and stable identifiers.
3. Capture row counts, soft-deleted counts, duplicate/conflict counts, period totals by currency and status, and counts of missing/invalid inputs. Avoid placing employee identifiers or actual amounts in public logs.
4. Record checksums or other approved integrity evidence for the export and its storage location.
5. Rehearse on a sanitized copy and verify restore access before proceeding.

### Stage B: additive schema only

1. Add only approved, documented fields or versioned child records; prefer additive changes compatible with the current application.
2. Do not change the meaning or storage of `items`, `manual_amount`, `amount`, contract salary fields, or invoice amount fields in the same release as the initial input contract.
3. Add constraints only after existing data has been profiled and all conflicts have an approved reconciliation decision.
4. Define explicit behavior for records with missing data. Do not populate legal classifications, tax eligibility, policy versions or currency values by guessing.
5. Ensure authorization and audit behavior ships with any sensitive field.

### Stage C: backfill and reconciliation

1. Backfill only fields whose values can be derived losslessly from authoritative records under an approved mapping.
2. Keep unresolved rows in a review queue with stable references; do not coerce unknown values into valid categories.
3. Compare before/after row counts, null/conflict counts, period totals by currency/status, and source links.
4. Verify all Open/Closed/Paid lifecycle invariants, duplicate-source constraints, immutable snapshots, and correction links.
5. Record all exceptions and obtain Finance/HR and specialist approval before proceeding.

### Stage D: compatibility and shadow run

1. Keep legacy readers/writers compatible until the replacement is proven and approved.
2. Run new input validation and any approved calculations in non-posting/shadow mode first.
3. Compare every result to independently reviewed expected fixtures; investigate differences rather than adjusting expected values to match the implementation.
4. Verify repeated runs are deterministic for the same inputs and policy version.
5. Confirm that an update to a contract or policy does not rewrite a historical closed/paid result.

### Stage E: rollout and rollback

1. Release behind an explicit feature gate and restrict access to approved users.
2. Monitor validation failures, missing evidence, reconciliation discrepancies and calculation-version mismatches without logging unnecessary sensitive data.
3. Define a stop/rollback trigger, responsible operator, communication path and recovery point before enabling the feature.
4. Prefer disabling the new path and retaining additive data over destructive rollback. Never drop source snapshots or audit history as part of an ordinary rollback.
5. If a migration rollback would delete financial history, refuse it until approved export and recovery procedures are completed.

## 4. Minimum automated test plan

| Area | Required assertions before merge |
| --- | --- |
| Migration safety | Fresh install and upgrade from supported schema versions; idempotency where applicable; constraint behavior on duplicates/conflicts. |
| Data preservation | Legacy JSON, baseline, amounts, payment dates, soft-delete state and source identifiers are preserved. |
| Effective dating | Start/end boundaries, contract/policy changes and historical versions are handled as approved. |
| Invalid/missing inputs | Missing or conflicting required facts produce explicit validation/review state, never an inferred statutory result. |
| Financial integrity | Decimal precision, currency checks, duplicate source prevention, totals and reconciliation reports match approved rules. |
| Lifecycle | `Open -> Closed -> Paid` constraints remain; paid/closed values and source snapshots are immutable; corrections are linked and auditable. |
| Authorization/privacy | Unauthorized reads/writes fail; sensitive evidence is not exposed in logs, generic snapshots, fixtures or exports. |
| Recovery | Backup/export restore and documented rollback/forward-recovery are rehearsed against the sanitized copy. |

Test expected statutory values only after a qualified reviewer has approved the versioned fixtures. Generic arithmetic tests do not prove legal compliance.

## 5. Merge and production evidence checklist

- [ ] Policy and input-contract approval references are attached to the change record.
- [ ] Migration diff reviewed independently by engineering and finance/data owner.
- [ ] Test suite and CI pass on the exact commit being merged.
- [ ] Before/after reconciliation report is retained in controlled storage.
- [ ] Restore/rollback rehearsal and recovery owner are recorded.
- [ ] Shadow-run discrepancies are resolved and signed off.
- [ ] Production feature gate, access roles, monitoring and stop conditions are documented.
- [ ] No statutory calculation is represented as approved unless the domain reviewer explicitly approved it.

## Current decision

**Not ready for schema implementation.** The current evidence supports documenting the migration controls, but not selecting a final statutory input schema or backfill mapping. Issue #340 remains blocked by approval prerequisites in #335-#339. This document does not authorize a database migration or change application behavior.