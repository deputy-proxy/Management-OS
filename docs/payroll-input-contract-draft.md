# Payroll input contract and schema-gap draft

**Status: historical engineering draft.** The current implemented effective-dated input persistence contract is documented in [`payroll-contract-inputs.md`](payroll-contract-inputs.md). This inventory remains useful for legacy-field context but is not a blocker to application-side schema, validation, UI, and test development. It does not establish Romanian legal rules or authorize statutory calculations.

## Contract boundaries

- Employee payroll and supplier/PFA invoices are distinct workflows. Vendor names, invoice descriptions, and synthetic examples do not establish legal or tax classification.
- A pay-period result must be reproducible from the captured inputs, source snapshots, policy version, and calculation version used at the time.
- Store only information required for the approved workflow. Medical and other sensitive evidence should remain access-restricted; payroll calculation records should refer to controlled evidence rather than copy document contents into general-purpose snapshots.
- Effective dates are business dates. The system timezone and calculation timestamp are separate metadata and must not change the legal effective date.
- Missing or conflicting required inputs must produce an explicit validation/review state, not a default rate, inferred tax status, or zero-value result that appears approved.
- Existing `EmployeePaySlip` controls remain requirements: unique employee/month, `Open -> Closed -> Paid`, `paid_on` required for Paid, immutable source-linked items, duplicate-source protection, and signed adjustments for corrections. Legacy `items` JSON and `manual_amount` must not be silently reinterpreted.

## Existing schema inventory

| Domain | Existing fields / behavior observed in repository | Gap or constraint before implementation |
| --- | --- | --- |
| Employee contract | `employee_contracts`: employee ID, start/end dates, contract type, integer legacy `base_salary` and `hourly_rate`, JSON `clauses`, soft deletion. Model casts the two amounts to decimal strings and clauses to an array. | Amount storage history and currency/basis semantics need reconciliation. Contract type vocabulary, overlapping contracts, salary basis, schedule/norm, occupation, and evidence reference are not a complete typed input contract. Do not assume the JSON clauses are approved tax-policy inputs. |
| Pay period | `employee_pay_slips`: employee ID, month, legacy JSON `items`, `manual_amount`, aggregate `amount`, optional due/paid dates in current workflow, status, soft deletion. Month normalizes to the first day; employee/month is unique. | No approved policy version, calculation version, input-set version, or reviewer approval reference is currently defined as a dedicated field. Determine whether these belong on a result record or linked immutable version records. |
| Source-linked line | `employee_pay_slip_items`: payslip ID, item type, source type/ID, period/source keys, occurrence date, description, decimal quantity/rate/amount, unit, currency, calculation snapshot. Rows cannot be updated/deleted. | Current rows support activity/adjustment evidence, not a complete statutory gross-to-net breakdown. Define approved result components separately without overwriting historical activity snapshots. |
| Holiday | `employee_holidays`: employee ID, start/end dates, status, soft deletion. Current workflow documents approved weekday ranges and explicit `hours_per_day` with an 8-hour default in the newer holiday implementation. | Approval authority, leave category semantics, source evidence, schedule interactions, and statutory treatment require review. The historical default is implementation behavior, not legal policy approval. |
| Medical leave | `employee_medical_leaves`: employee ID, start/end dates, document field, soft deletion. | Sensitive document handling, leave/certificate classification, approval, duration/amount inputs, access policy and retention need a controlled specification. Do not copy medical details into general payroll snapshots. |
| Timesheet | `employee_timesheets`: employee ID, date, start/end timestamps, category, integer hours, session ID, soft deletion. | Define authoritative source, approval state, precision, conflict resolution, timezone rules and whether it is applicable to each pay basis. Existing activity sources are not automatically interchangeable with timesheet rows. |
| Tax and benefit facts | No approved typed contract was established by this inventory. Legacy clauses and manual breakdowns exist. | Identify only the eligibility facts required by reviewer-approved policy, their authoritative source, effective dates, evidence reference and restricted visibility. No rates, thresholds or eligibility are inferred here. |
| Approval and audit | Contract activity logging currently records selected fields (`start_date`, `end_date`, `contract_type`); payslip source items capture immutable calculation snapshots; payslip status guards exist. | Define preparer/reviewer/approver identity, approval timestamps, policy approval evidence, input-change events, access audit, correction linkage and retention. Confirm sensitive field changes are adequately audited without logging unnecessary values. |

Evidence for this inventory: `app/Models/EmployeeContract.php`, `EmployeePaySlip.php`, `EmployeePaySlipItem.php`, the employee contract/payslip/timesheet/leave migrations, `docs/employee-payslip-workflow.md`, and `docs/phase4-payroll-next-steps.md`.

## Proposed logical input envelope

This is a field-group proposal, not a final API or database schema. Field names and types must be confirmed against source documents and approved policy before code is written.

| Group | Proposed minimum fields | Validation and source-of-truth rule |
| --- | --- | --- |
| Identity and arrangement | Internal employee ID; contract ID; reviewed worker/contract category; source-document reference | IDs must resolve to active controlled records. Worker category is evidence-backed and reviewer-approved, never inferred from a display name. |
| Contract terms | Effective start/end; approved salary basis; amount as a decimal string; ISO currency; schedule/norm; occupation or role only if policy requires it | Require valid effective range and an explicitly approved basis. Resolve overlapping or missing terms to review rather than selecting a rate by incidental record ID. |
| Pay period | `YYYY-MM`; policy version ID; calculation version; input capture timestamp; calculation timestamp; lifecycle state | One period per employee unless an approved design specifies otherwise. Capture immutable version references used for the result. |
| Time and activity | Source type and stable source ID; occurrence date/time; quantity and unit; source status/approval; source snapshot/reference | Reject duplicate source use and unapproved/incomplete sources. Preserve source provenance and timezone meaning. |
| Absence and leave | Category; effective dates or approved quantity; approval state; restricted evidence reference | Validate overlap and approval. Medical data is restricted; the calculation layer receives only approved facts needed by the policy. |
| Eligibility facts | Only facts explicitly required by approved policy; effective dates; evidence reference; verification state | Unknown or expired facts block or route for review where required; do not silently treat unknown as false/true. |
| Result and review | Component identifiers and decimal amounts; currency; policy/rule version; explanation; preparer/reviewer/approver; approval timestamp; correction reference | Results must be reproducible and explainable. No component definition or expected statutory value is approved by this draft. |

### Representation and precision

- Use decimal strings at external boundaries for monetary values; avoid binary floating-point as the authoritative representation.
- Currency must be explicit for every amount. The existing activity ledger defaults currency to RON, but that default alone does not prove every legacy contract amount is RON.
- Specify precision and rounding stage per component only in the approved policy. Existing activity code's two-decimal half-up rounding is current behavior, not a blanket statutory rounding rule.
- Keep the legacy `items` JSON and `manual_amount` distinct from any future normalized statutory components until an owner-approved, reconciled mapping exists.
- Never rewrite closed/paid periods to reflect later contract or policy changes. Corrections must preserve original inputs/results and link the adjustment to the original record.

## Schema gap register

| Gap | Required decision/evidence | Safe engineering action before approval |
| --- | --- | --- |
| Contract pay basis and amount units | HR/finance confirms meaning, currency, effective date, schedule and source document for real contracts. | Inventory and report gaps; do not convert legacy integer values or change field semantics. |
| Worker/contract categories and overlap | HR and qualified specialist approve allowed categories and conflict resolution. | Add validation proposal/tests for missing or conflicting facts only after category vocabulary is agreed. |
| Policy and calculation version linkage | Reviewer approves version identity, effective dates, approval metadata and historical recalculation policy. | Keep statutory calculations disabled; design a version reference without fabricating rules. |
| Time/leave source precedence | HR approves source of truth, approval process, units, overlap and corrections. | Document source mappings; do not merge duplicate sources or infer hours. |
| Sensitive evidence and authorization | Data owner/security reviewer approves visibility, evidence references, audit events and retention. | Minimize stored data and restrict document references; do not place sensitive content in fixtures or logs. |
| Historical baseline and legacy breakdown | Finance reconciles sample records and signs off mapping and totals. | Preserve legacy JSON and baseline; no backfill or destructive migration. |
| Result approval and correction | Finance/HR defines roles, evidence, locks, corrections and release gate. | Preserve current status/immutability controls and test them; do not mark results approved by default. |

## Deferred policy decisions (not blockers to application-side data-contract work)

Policy-specific values and statutory calculation behavior must remain configurable and must not be guessed. These decisions do not block building typed nullable inputs, effective dating, permissions, validation, and synthetic regression tests. When a statutory calculation path is enabled, its configuration must explicitly identify missing parameters and block unsupported calculations. A migration does not imply that statutory rules or production use have been approved.

For any future statutory rule configuration or production rollout, the review package should include:

1. A reconciled real roster/contract inventory held in an access-controlled location outside Git, with unresolved cases flagged.
2. Approved field definitions, types, requiredness, validation, source of truth, effective dating, authorization and audit requirements.
3. A schema-gap decision list with explicit accept/defer/reject outcomes.
4. Specialist-checked expected-result fixtures and effective-date/rounding boundary cases.
5. A historical-data export, before/after reconciliation and tested recovery plan for any data migration.

Synthetic fixtures are useful for testing validation and missing-input behavior only. They are not evidence of the actual workforce, a legal classification, approved policy, or expected statutory output.

## Status

**Historical draft.** Issue #338 now has an implemented effective-dated input model and form. The current schema and validation behavior are described in `docs/payroll-contract-inputs.md`. Statutory calculations still require explicit configured parameters and do not infer missing values.