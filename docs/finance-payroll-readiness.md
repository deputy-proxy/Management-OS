# Finance and payroll capability matrix

**Status: evidence-backed technical draft; finance/payroll stakeholder review is pending.** This document describes repository behavior, not statutory accounting or payroll advice, production reconciliation, or approval to change financial schemas.

**Status meanings:** *Implemented (scoped)* means the named workflow and its tested safeguards exist, not that accounting is complete; *Partial* means a useful operational workflow exists but material semantics or controls remain unresolved; *Missing* means no implementation was found in the inspected repository; *Unverified* means source code alone cannot establish real-world or production behavior.

## Capability status and evidence index

The detailed matrix below records actual workflows, known limitations, controls and evidence. This index makes implementation status explicit and adds the important capabilities that do not fit into the existing detailed rows.

| Capability | Status | Actual workflow / source of truth | Validation, authorization, audit and automation | Evidence and unresolved gap |
| --- | --- | --- | --- | --- |
| Financial accounts and reported balances | **Partial** | Staff maintain account metadata and dated balance snapshots; a snapshot is stored input, not a calculated ledger balance. | Resource permissions and model activity logging exist; no verified bank-feed or statement import. | [`FinancialAccount`](../app/Models/FinancialAccount.php), [`FinancialAccountBalance`](../app/Models/FinancialAccountBalance.php), [`FinancialAccountResource`](../app/Filament/Resources/FinancialAccounts/FinancialAccountResource.php). Balance provenance and independent reconciliation remain unverified. |
| Product costs and product revenue | **Partial** | Product-resource allocations create allocated-cost rows; product revenue can link to a product and customer invoice. | Allocation is recalculated in application code; no shared currency or approved accounting basis is established. | [`ProductCostService`](../app/Services/ProductCostService.php), [`ProductCost`](../app/Models/ProductCost.php), [`ProductRevenue`](../app/Models/ProductRevenue.php). Confirm allocation eligibility, exact-total rules, residual rounding and correction history. |
| Finance dashboard | **Partial** | Aggregates recorded transactions by stored type and account currency and counts invoice statuses by month. | Permission-gated page; soft-deleted rows excluded; automated summaries are covered by feature tests. | [`FinancialOverviewData`](../app/Services/FinancialOverviewData.php), [`FinancialOverviewDataTest`](../tests/Feature/FinancialOverviewDataTest.php), [`docs/financial-overview.md`](financial-overview.md). It is not a cash-balance, margin, receivables/payables, VAT or reconciled-statement report. |
| Reconciliation diagnostics | **Implemented (read-only scope)** | `database:integrity-audit` and `finance:reconciliation-audit` report configured integrity, payslip and transaction-link anomalies. | Commands are diagnostic and non-mutating; regression coverage exists. A clean result does not prove settlement or legal correctness. | [`AuditFinanceReconciliation`](../app/Console/Commands/AuditFinanceReconciliation.php), [`FinanceReconciliationAuditTest`](../tests/Feature/FinanceReconciliationAuditTest.php), [`docs/database-integrity.md`](database-integrity.md). Define discrepancy owners, severity and close evidence. |
| Contractor payroll | **Missing** (vendor administration is partial) | Vendor, vendor-contract and vendor-invoice records support supplier administration; no separate contractor-payroll calculation or contractor payslip workflow was found. | Vendor invoice permissions do not establish payroll authorization, classification or withholding rules. | [`Vendor`](../app/Models/Vendor.php), [`VendorContract`](../app/Models/VendorContract.php), [`VendorInvoice`](../app/Models/VendorInvoice.php). Decide how employees, individual contractors and vendor entities differ before implementation. |
| Statutory Romanian payroll and tax calculations | **Missing** | Existing payslip JSON can hold manually maintained breakdown entries; tax records are manual records, not a tax engine. | No signed, effective-dated statutory rule pack with independently checked examples was found. | [`docs/employee-payslip-workflow.md`](employee-payslip-workflow.md), [`docs/financial-rules-decision-register.md`](financial-rules-decision-register.md). A qualified Romanian payroll/accounting owner must provide rules, dates, examples and sign-off. |
| Common money, currency and rounding contract | **Specified in draft; implementation missing** | [Canonical field-level contract](financial-data-contract.md) maps current fields and compatibility gaps; no common runtime contract is implemented yet. | No FX conversion is evidenced. Tests prove code behavior, not statutory compliance. | [`docs/financial-data-contract.md`](financial-data-contract.md), [`docs/financial-rules-decision-register.md`](financial-rules-decision-register.md). Legacy mapping and migrations remain blocked pending review and reconciliation. |
| Unified finance approval, close and settlement | **Missing / Unverified** | Payslip lifecycle guards exist, but a unified approval workflow for invoices, payments, reconciliations and finance close was not established. | Selected resources have permissions and activity logs; real operator practice, evidence retention and segregation of duties are not verified by source alone. | [`docs/authorization.md`](authorization.md), [`docs/audit-logging.md`](audit-logging.md). Name preparer/reviewer/approver roles and define settlement/close evidence. |
| Bank statement ingestion | **Missing** | No verified bank PDF ingestion pipeline or bank-feed workflow was found. | No approved format specification, sanitized fixtures, parser tests or import approval workflow was found. | [`docs/financial-rules-implementation-readiness.md`](financial-rules-implementation-readiness.md). Document formats against sanitized samples before implementing a parser; keep parsing separate from posting. |

## Capability matrix

| Area | Verified current behavior / user workflow and source of truth | Known limitation / decision needed | Control, authorization, audit and evidence |
| --- | --- | --- | --- |
| Employee contracts and rates — **Partial** | Contracts store effective dates, contract type, base salary, hourly rate and JSON clauses. Payslip activity selects an active contract for the activity date; conflicting active contracts are resolved by latest start date then highest ID. | Whether overlapping contracts are valid and how base salary should affect monthly payroll has not been established by this code. | Contract owner reviews overlapping contracts and effective-dated rates before each close. Preserve the contract/rate snapshot on every line. |
| Monthly payslip generation — **Implemented (scoped); overall payroll Partial** | Calendar-month payslips are unique by employee/month. `employee-payslips:generate --month=YYYY-MM` is idempotent; the scheduler is documented for day 1 at 00:05 application time. Eligible employees have a non-deleted contract overlapping the month. | Generation creates the slip and approved automatic items; it does not calculate statutory payroll. Scheduler execution and failed-run alerting are operational controls, not proven by a unit test alone. | Record generated month, run result, counts and operator/recovery evidence. Re-run only after reviewing the period; command is idempotent. |
| Activity pay — **Implemented (scoped)** | Sessions, completed session assistance, completed test simulations, approved weekday holiday hours and configured own-equipment reimbursement can create RON source lines. Rate, formula, source IDs and timestamps are snapshotted. Duplicate source keys prevent double payment across periods/employees. | Confirm whether every activity type is contractually payable, how overlaps/overtime/leave should be treated, and who approves source eligibility. | Reconcile source activities to line items by employee, date, source ID and contract rate. Investigate every missing/duplicate line; never rewrite immutable source snapshots. |
| Legacy payroll breakdown — **Partial** | `employee_pay_slips.items` JSON and `manual_amount` are preserved separately from normalized `employee_pay_slip_items`. Existing amount was copied into the baseline by migration. | Legacy JSON is not losslessly mappable to source lines from current evidence. The total does not re-sum JSON, avoiding likely double-counting. | Keep both representations until a payroll owner approves a mapping and before/after reconciliation. Export and preserve historical JSON. |
| Payslip lifecycle and corrections — **Implemented (scoped)** | `Open → Closed → Paid`; Paid requires `paid_on`. Closed/Paid amounts and legacy breakdown are locked by the model. Source rows cannot be edited/deleted; corrections are signed adjustment rows with a reason and reference to the original. | Approval authority, evidence required for closure/payment, and period-close timing are not defined in code. Model-level rules do not replace database/operational access controls. | Reconcile and approve before Closed; reconcile bank/payment evidence before Paid. For corrections, preserve the original and attach reason/source evidence. |
| Customer invoices — **Partial** | Customer invoices link to customer and customer contract and store `amount_without_vat`, `vat`, month, status, invoice reference and identifier. Product revenue has a one-to-one relationship from the invoice side. | No verified formula defines whether `vat` is a rate or amount, how `amount_without_vat` relates to total, which statuses mean issued/paid, or how credits/voids work. | Reconcile invoice register to approved invoice documents and source contracts. Do not derive VAT totals or payment status until semantics and rounding are approved. |
| Vendor invoices — **Partial** | Vendor invoices link to vendor and vendor contract and store `amount_without_vat`, `vat`, currency, month, status, attachment/reference, `amount`, invoice number and identifier. | Currency and numeric precision are inconsistent with a verified common monetary contract: the migration history uses integer columns for several amounts. Relationship between `amount`, VAT and net amount is undocumented. | Reconcile each invoice against supplier document, currency and contract. Decide amount semantics, precision, rounding and exchange-rate policy before migration or aggregation. |
| Transactions and tax records — **Partial** | Transactions store financial account, optional vendor invoice, transaction type, integer amount, payment method, timestamp, customer invoice and tax references. Tax records store category, month, integer amount, status, due date and paid date. A read-only database audit checks orphan references. | Transaction type values, sign convention, split/partial payment behavior, tax category semantics and whether both invoice links may be set are not established. This is not proven to be a double-entry ledger. | Reconcile transaction records to bank/payment evidence and linked invoice; document partial payments, reversals, refunds and allocation rules first. Do not present this model as a general ledger. |
| Statutory payroll — **Missing** | The existing JSON may contain manually maintained statutory breakdown entries. | No verified, effective-dated calculation rules for CAS, CASS, income tax, exemptions, benefits, proration or statutory reporting are implemented. | A qualified Romanian payroll/accounting owner must supply the applicable rules, effective dates, examples and sign-off before automated calculations are introduced. |
| Currency, precision and rounding — **Missing** | Payslip line items use decimal columns, RON, and half-up rounding to two decimal places. Several invoice, transaction and tax schemas use integer amounts; vendor invoice has a currency field while customer invoice/transaction/tax schemas do not. | No single cross-module currency/precision/rounding contract exists. | Owner must approve currency scope, minor units, rounding stage, exchange-rate source/date and legacy conversion plan. No broad amount-column migration without a before/after reconciliation and recovery plan. |
| Approval and close — **Missing / Unverified** | Payslip state transitions and locked historical records are implemented. | There is no evidenced unified approval workflow for invoices, payments, payroll, reconciliations or finance period close. | Assign preparer/reviewer/approver roles, segregation of duties, close checklist and evidence-retention rules. |

## Reconciliation controls

### Automated, read-only checks

Run:

```bash
php artisan database:integrity-audit
php artisan finance:reconciliation-audit
```

- `database:integrity-audit` checks configured duplicate and orphan-reference invariants, including invoice and transaction relationships.
- `finance:reconciliation-audit` checks payslip totals/dates/periods/source links, canonical amount completeness and decimal precision, canonical currency-code and classification formats, transaction/account and balance/account currency consistency, and configured invoice total/VAT rules. It reports only record counts and limited record-ID samples, not monetary or other sensitive field values. Invoice-linked transaction counts are informational only; they are not settlement evidence.
- Both commands are diagnostics only. They do not fix records. A non-zero exit requires review and a documented resolution before close.
- These checks do not prove that source activities are complete, that a payment occurred, or that a tax calculation is legally correct.

### Period-close checklist

1. Confirm the target period, timezone and complete source-data window.
2. Review the generation command output and compare eligible employees and generated payslips against the contract roster.
3. Run both read-only audits and retain their output with the period-close evidence.
4. For each employee, compare source activities, contract/rate snapshots, approved holiday ranges, reimbursement clauses, legacy JSON and manual baseline. Explain every difference.
5. Review every signed adjustment against the original line, reason and supporting evidence.
6. Reconcile invoice registers to invoice documents; reconcile transactions to bank/payment evidence only after transaction-type and allocation rules are confirmed.
7. Have a separate authorized reviewer approve the period. Close payslips only after discrepancies are resolved or explicitly accepted.
8. Mark Paid only when payment evidence and date are available. Keep historical records immutable; corrections belong in documented adjustments or approved correcting records.
9. Export a before/after record count and amount summary before any schema or data migration; test restore/recovery on a copy before production changes.

## Required owner decisions before production claims

- Named payroll/accounting owner and independent approver.
- Effective-dated, authoritative Romanian payroll rules and representative signed-off examples.
- Meaning and allowed values for invoice, transaction, tax and payment statuses/types.
- Invoice VAT/total formula, rounding, currency and exchange-rate policy.
- Partial payment, refund, credit note, reversal, bad debt and payment-allocation rules.
- Payroll close, payment confirmation, correction approval and retention procedures.
- Whether a double-entry ledger or statutory accounting integration is required.
- Approved migration plan for integer monetary fields and legacy JSON, including exports, before/after totals and rollback/recovery evidence.

Until these are decided and reviewed by the designated owner, Management-OS should be described as an internal operational finance and source-linked payroll workflow, not a complete statutory payroll engine or general ledger.
## Current readiness decision (2026-10-18)

**Decision: conditional NO-GO for statutory payroll or general-ledger claims.** The repository is suitable only for its documented internal operational workflow, with the manual controls below. A successful test suite or a clean diagnostic report is not proof of statutory correctness, completeness of source activity, or payment settlement.

### Currency, precision and rounding contract that is actually implemented

- Generated payslip source lines are denominated in `RON` and store `quantity` and `unit_rate` at two decimal places and `amount` at two decimal places.
- The current payroll service uses PHP `round(value, 2, PHP_ROUND_HALF_UP)` for quantity and money calculations. Source calculations are rounded to two decimal places before the stored line amount; holiday calculations round each covered weekday before summing the holiday total.
- Contract selection for a dated source activity uses contracts covering that activity date, ordered by latest `start_date`, then highest ID. This deterministic tie-breaker is implemented behavior, **not** approval that overlapping contracts are valid.
- The normalized payslip source-line currency is fixed to `RON`. No FX conversion is implemented.
- Customer invoices, vendor invoices, transactions and tax records do not yet share a verified monetary contract. Legacy columns remain integer-valued; additive nullable decimal/currency-code fields are available, but their population is gated by exact reviewed mappings. Customer invoices have legacy integer `amount_without_vat`/`vat`; vendor invoices have legacy integer `amount_without_vat`/`vat` and `amount`, plus a currency field; transaction and tax amounts are integers. The meaning of those amounts, VAT fields, sign convention and rounding stage remains unapproved.
- Do not infer a VAT total, invoice gross total, net payroll, statutory deduction or exchange rate from these fields. Do not migrate monetary column types until the owner signs off a conversion specification with before/after totals and a tested recovery path.

### Expanded read-only reconciliation command

`php artisan finance:reconciliation-audit` now checks:

- Stored payslip totals against `manual_amount + normalized source-line amounts`.
- Paid payslips missing `paid_on`.
- Duplicate employee/month payslips, including retained soft-deleted records.
- Missing source records for recognized payslip line types and unrecognized `source_type` values.
- Transactions linked to both a customer invoice and a vendor invoice, which requires semantic review rather than an inferred correction.
- Informational active transaction counts grouped by linked invoice ID. These are trace reports only, not assertions that the invoice is paid or that transaction totals equal invoice totals; partial-payment, sign, allocation and reversal semantics are not approved. Monetary values are withheld from command output.

The command is read-only. It returns a non-zero exit code when configured discrepancies are found. Invoice semantic checks remain unresolved until an exact rule is explicitly approved in `config/financial-normalization.php`. Run it alongside `php artisan database:integrity-audit`, retain the full output with close evidence, and trace every finding to the named record IDs before correction. The command reports no raw amounts, invoice contents, employee identifiers, or free-text descriptions. Unknown historical source types should be mapped only after review, not silently discarded.

### Owner sign-off and production gate

No qualified Romanian payroll/accounting owner or independent approver is identified in the repository, and no signed statutory-rule specification was provided for this work. Therefore CAS, CASS, income tax, exemptions, benefits, proration, statutory reporting and tax treatment remain manual/out of scope for automatic calculation. Before any statutory payroll release, the owner must attach: (1) authoritative source and effective date for each rule, (2) approved examples covering boundary dates and rounding, (3) expected results independently checked by the owner, and (4) named preparer/reviewer/approver responsibilities. The release remains blocked until that evidence exists.

### Historical data and recovery

- Paid/closed payslips and normalized source items remain immutable; corrections use signed adjustment items with a reason and link to the original.
- Legacy JSON remains separate from normalized line items. Do not auto-map old JSON into new source rows.
- No broad finance-schema migration/backfill is included in this issue. Before any future monetary or legacy-data migration: export affected records; record row counts and per-period totals by currency/status; run the conversion on a production-like copy; compare before/after values and exception rows; verify a tested restore/recovery path; obtain owner approval; and retain the evidence with the release.
- Existing invoice and transaction data cannot currently establish settlement status reliably. Reconcile against issued invoice documents and bank/payment evidence under an approved allocation policy.

### Operational decision

The capability matrix and diagnostics define the software boundary, but the repository cannot itself supply human accounting sign-off. The explicit decision is **not ready to be represented as a statutory payroll engine, VAT calculator, payment ledger, or double-entry general ledger**. The operational payroll workflow may be used only under the documented manual reconciliation and approval controls until the owner-signed rule pack and payment/invoice semantics are in place.


## Unresolved decisions and stakeholder review

No named reviewers for the capability matrix or signed statutory-rule pack are identified in the repository. These decisions must remain open rather than being inferred from implementation details.

| Decision | Required evidence / decision | Accountable reviewer | Status |
| --- | --- | --- | --- |
| Money units, currency, precision and rounding | Field-level meaning, approved examples, exchange-rate policy and legacy mapping | Finance/accounting owner with independent reviewer | Open; prerequisite to #306/#307 |
| Transaction direction, reversals and payment allocation | Type/sign vocabulary, partial payments, transfers, refunds, credits, mismatches and idempotency | Finance/accounting owner | Open |
| Invoice and tax lifecycle | VAT/net/gross formula, status vocabulary, credit/void rules and settlement evidence | Finance/accounting owner / tax specialist | Open |
| Product-cost allocation | Eligible costs/overhead, required allocation total, residual rounding and correction history | Product/finance owner | Open |
| Employee and contractor treatment | Contract overlaps, base salary, payable activities, leave/overtime, worker classification and withholding | HR/payroll owner and qualified Romanian payroll specialist | Open |
| Period close and approvals | Preparer/reviewer/approver, close checklist, correction approval, settlement evidence and retention | Finance owner and independent approver | Open |
| Bank statement import | Supported formats, sanitized samples, independently checked expected values and preview/approval controls | Finance operations owner | Open; document format first |

## Roadmap and implementation boundaries

This matrix supports [issue #305](https://github.com/deputy-proxy/Management-OS/issues/305). It is linked from the [project README](../README.md) and the [financial-rules implementation readiness and sequencing plan](financial-rules-implementation-readiness.md).

1. **#305 capability matrix:** inventory current workflows, controls, gaps and evidence; obtain stakeholder review.
2. **#306 canonical financial data contract:** map fields and consumers only after financial semantics are approved.
3. **#307 currency/precision migration plan:** quantify legacy data, define reconciliation/recovery and obtain approval.
4. **#308 schema changes:** implement only after the contract and migration plan are approved.

No schema migration, amount conversion, historical backfill, destructive deletion or broad finance/payroll behavior change is authorized by this matrix. Existing features should be extended rather than rebuilt wholesale.

**Stakeholder review state: pending.** The matrix is ready for finance/accounting and payroll review, but the repository does not identify named reviewers or record approval of this matrix. Before issue #305 is closed, reviewers should confirm/correct statuses and workflows, record decisions with owner/date, and resolve the outstanding review criterion. The current technical boundary remains: internal operational finance records, read-only diagnostics and source-linked activity-payroll workflows exist; a statutory payroll engine, VAT calculator, settlement ledger and double-entry general ledger are not verified.
