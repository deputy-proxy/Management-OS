# Phase 4 payroll and invoice workflow: next steps

**Planning baseline:** 2026-10-25  
**Business inputs received:** employee-only workforce scope; approximately 6–20 employees; supplier/contractor payments are invoice-based; a Romanian payroll/accounting reviewer is available; both employee payroll and invoice workflows are in scope.

**Important limitation:** these answers establish the initial project scope, not a verified HR roster or a legal classification of every counterparty. No statutory payroll calculation should be enabled until the reviewer approves the policy and expected-result fixtures.

## Delivery sequence

### 1. Confirm the operational baseline with HR/finance and the reviewer

Collect a controlled, non-repository working export for reconciliation. Do not commit names, personal identifiers, salary details, bank data, medical data, or signed contracts to Git.

- Reconcile the 6–20 employee estimate to active employee records and signed CIM contracts/amendments.
- For each employee, confirm contract effective dates, contract type, gross salary basis and currency, schedule/norm, job/occupation, relevant benefits/allowances, and the authoritative source document.
- Confirm which time/activity, absence and leave sources are authoritative and who approves each before payroll close.
- Confirm that invoice-based payments are supplier invoices, then classify each counterparty from source documents as company, PFA, other individual, or unresolved. Do not infer PFA status from a vendor name or invoice alone.
- Record the reviewer role, review date, open legal interpretations, preparer/reviewer/approver roles, and period-close owner.

**Exit condition:** a reconciled roster and counterparty classification register exist outside the repository, with unresolved records explicitly flagged.

### 2. Have the reviewer approve employee payroll policy (#337)

Prepare versioned policy decisions and expected-result fixtures for the actual arrangements, including only applicable cases:

- gross-to-net calculation order, taxable bases, contribution/tax rules, exemptions and deductions;
- rounding precision and the stage at which rounding occurs;
- effective dates, mid-month join/leave or salary changes, part-time/hourly cases, absence/leave, overtime/night work, and benefits/allowances;
- monthly close, approval, payment evidence, corrections and historical recalculation boundaries;
- source version, policy version and calculation explanation retained with each result.

The Romanian payroll/accounting reviewer must supply or approve representative expected results and edge cases. Finance/HR approves company-specific choices. Record approval and effective dates in a durable decision record.

**Exit condition:** signed/traceable reviewer approval and fixtures exist. Until then, Management-OS payslips remain operational activity-based records, not statutory payroll calculations.

### 3. Define the employee payroll input contract (#338)

Map each input to the current schema before proposing migrations. The initial contract should cover:

| Input group | Minimum contract | Existing area to inspect |
| --- | --- | --- |
| Worker and contract | Employee ID, contract ID/type, effective start/end, source-document reference | `Employee`, `EmployeeContract` |
| Pay basis | Gross salary or hourly rate, currency, salary basis, working norm/schedule, effective dates | `EmployeeContract` and its migrations |
| Pay period | Calendar month, policy version, calculation timestamp, status | `EmployeePaySlip` |
| Time and activity | Source type/ID, date, quantity/hours, approval state, source snapshot | `EmployeeTimesheet`, `EmployeePaySlipItem`, `EmployeePaySlipService` |
| Leave and absences | Type, dates/quantity, approval/evidence reference, restricted visibility where sensitive | Employee leave models and approved source workflow |
| Tax/benefit facts | Only reviewer-approved eligibility inputs, effective dates and evidence references | Currently requires a gap analysis; do not assume JSON clauses are a typed policy contract |
| Result and audit | Gross components, deductions, net result, policy/rule version, explanation, approver and correction link | Current payslip stores amounts and activity snapshots; statutory breakdown needs an approved design |

For each field, specify type, required/optional status, validation, source of truth, effective dating, access restrictions, audit behavior and correction semantics. Explicitly identify missing schema. Do not repurpose legacy integer amount fields or silently change the meaning of `items` / `manual_amount`.

**Exit condition:** HR/finance and the reviewer approve the input contract and the schema-gap list before migrations or engine code begin.

### 4. Define the invoice-based supplier workflow separately

- Reconcile vendor, vendor-contract, invoice, transaction and payment-allocation records.
- Establish required invoice identity, issue/due dates, currency, net/VAT/gross amount semantics, approval state, payment allocation, reversal/correction links and evidence.
- Record verified counterparty legal/tax type and effective dates from source evidence, with restricted access to identifiers.
- Route PFA/individual tax questions to the reviewer as a separate decision path; employee deductions and payslip statuses must not be reused for invoices.
- Resolve legacy amount-field precedence before adding validation or migration behavior.

**Exit condition:** finance approves the invoice lifecycle and reconciliation rules; any actual PFA arrangement has a separate, reviewed handling specification.

### 5. Implement only after approvals, with regression protection

- Preserve immutable source-linked payslip items and duplicate-source protection; implement a distinct draft/review/approval/close workflow with immutable snapshots, audit history, concurrency guards and signed corrections. Keep new Paid transitions disabled until verified payment evidence is integrated.
- Add tests from approved fixtures first, including effective-date boundaries, rounding, duplicate inputs, authorization, close/lock behavior and corrections.
- Keep employee payroll and supplier invoices as distinct workflows and permissions.
- Run focused tests and the repository CI gates; record the actual results. Do not claim validation while dependencies are missing or tests have not run.
- Roll out in shadow/reconciliation mode against accountant-approved calculations before enabling any production statutory calculation.

## Immediate handoff checklist

- [ ] HR/finance: provide a reconciled, access-controlled roster and contract evidence for the 6–20 employees.
- [ ] HR/finance: identify invoice counterparties and source-supported legal/tax classifications; flag unknowns.
- [ ] Payroll/accounting reviewer: approve effective-dated policy decisions and expected-result fixtures.
- [ ] HR/finance + reviewer: approve the payroll input contract and schema-gap list.
- [ ] Finance: approve supplier invoice/payment lifecycle and legacy amount-field precedence.
- [ ] Engineering: add tests from approved fixtures, then implement the smallest schema/service changes that satisfy the contract.

## Current blockers

The workforce-size and workflow answers are planning inputs, not source evidence. Actual contracts/roster, counterparty classifications, salary terms, reviewer-approved calculation fixtures and approval records are still required. No statutory rates, tax formulas or PFA classifications are inferred in this plan.

## Synthetic demo fixtures

`tests/Fixtures/Payroll/synthetic-inputs.json` provides fictional employee, contract, approved-hours, leave, supplier-invoice and workflow examples for development and review. All IDs and amounts are fabricated. It deliberately includes a mid-period contract start and an unresolved individual/PFA counterparty so that missing policy/classification cannot silently turn into a calculation.

The fixture is a **proposed example contract**, not an approved production schema, seed data, or source of Romanian statutory rates. Amounts are decimal strings to avoid floating-point ambiguity in sample interfaces. The sample invoice's net/VAT/gross arithmetic is only an arithmetic example; it does not approve a VAT rate or tax category. No fixture contains a statutory net-pay expectation. Do not load these records into production or infer that the sample employee counts, salary values, contract types, leave, hours, or supplier classifications describe the real business.
