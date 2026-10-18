# Finance and payroll readiness assessment

**Status: technical assessment draft. Domain-owner approval is outstanding.** This document describes what the repository demonstrably implements; it is not statutory accounting or payroll advice.

## Capability matrix

| Area | Verified current behavior | Known limitation / decision needed | Control and evidence |
| --- | --- | --- | --- |
| Employee contracts and rates | Contracts store effective dates, contract type, base salary, hourly rate and JSON clauses. Payslip activity selects an active contract for the activity date; conflicting active contracts are resolved by latest start date then highest ID. | Whether overlapping contracts are valid and how base salary should affect monthly payroll has not been established by this code. | Contract owner reviews overlapping contracts and effective-dated rates before each close. Preserve the contract/rate snapshot on every line. |
| Monthly payslip generation | Calendar-month payslips are unique by employee/month. `employee-payslips:generate --month=YYYY-MM` is idempotent; the scheduler is documented for day 1 at 00:05 application time. Eligible employees have a non-deleted contract overlapping the month. | Generation creates the slip and approved automatic items; it does not calculate statutory payroll. Scheduler execution and failed-run alerting are operational controls, not proven by a unit test alone. | Record generated month, run result, counts and operator/recovery evidence. Re-run only after reviewing the period; command is idempotent. |
| Activity pay | Sessions, completed session assistance, completed test simulations, approved weekday holiday hours and configured own-equipment reimbursement can create RON source lines. Rate, formula, source IDs and timestamps are snapshotted. Duplicate source keys prevent double payment across periods/employees. | Confirm whether every activity type is contractually payable, how overlaps/overtime/leave should be treated, and who approves source eligibility. | Reconcile source activities to line items by employee, date, source ID and contract rate. Investigate every missing/duplicate line; never rewrite immutable source snapshots. |
| Legacy payroll breakdown | `employee_pay_slips.items` JSON and `manual_amount` are preserved separately from normalized `employee_pay_slip_items`. Existing amount was copied into the baseline by migration. | Legacy JSON is not losslessly mappable to source lines from current evidence. The total does not re-sum JSON, avoiding likely double-counting. | Keep both representations until a payroll owner approves a mapping and before/after reconciliation. Export and preserve historical JSON. |
| Payslip lifecycle and corrections | `Open → Closed → Paid`; Paid requires `paid_on`. Closed/Paid amounts and legacy breakdown are locked by the model. Source rows cannot be edited/deleted; corrections are signed adjustment rows with a reason and reference to the original. | Approval authority, evidence required for closure/payment, and period-close timing are not defined in code. Model-level rules do not replace database/operational access controls. | Reconcile and approve before Closed; reconcile bank/payment evidence before Paid. For corrections, preserve the original and attach reason/source evidence. |
| Customer invoices | Customer invoices link to customer and customer contract and store `amount_without_vat`, `vat`, month, status, invoice reference and identifier. Product revenue has a one-to-one relationship from the invoice side. | No verified formula defines whether `vat` is a rate or amount, how `amount_without_vat` relates to total, which statuses mean issued/paid, or how credits/voids work. | Reconcile invoice register to approved invoice documents and source contracts. Do not derive VAT totals or payment status until semantics and rounding are approved. |
| Vendor invoices | Vendor invoices link to vendor and vendor contract and store `amount_without_vat`, `vat`, currency, month, status, attachment/reference, `amount`, invoice number and identifier. | Currency and numeric precision are inconsistent with a verified common monetary contract: the migration history uses integer columns for several amounts. Relationship between `amount`, VAT and net amount is undocumented. | Reconcile each invoice against supplier document, currency and contract. Decide amount semantics, precision, rounding and exchange-rate policy before migration or aggregation. |
| Transactions and tax records | Transactions store financial account, optional vendor invoice, transaction type, integer amount, payment method, timestamp, customer invoice and tax references. Tax records store category, month, integer amount, status, due date and paid date. A read-only database audit checks orphan references. | Transaction type values, sign convention, split/partial payment behavior, tax category semantics and whether both invoice links may be set are not established. This is not proven to be a double-entry ledger. | Reconcile transaction records to bank/payment evidence and linked invoice; document partial payments, reversals, refunds and allocation rules first. Do not present this model as a general ledger. |
| Statutory payroll | The existing JSON may contain manually maintained statutory breakdown entries. | No verified, effective-dated calculation rules for CAS, CASS, income tax, exemptions, benefits, proration or statutory reporting are implemented. | A qualified Romanian payroll/accounting owner must supply the applicable rules, effective dates, examples and sign-off before automated calculations are introduced. |
| Currency, precision and rounding | Payslip line items use decimal columns, RON, and half-up rounding to two decimal places. Several invoice, transaction and tax schemas use integer amounts; vendor invoice has a currency field while customer invoice/transaction/tax schemas do not. | No single cross-module currency/precision/rounding contract exists. | Owner must approve currency scope, minor units, rounding stage, exchange-rate source/date and legacy conversion plan. No broad amount-column migration without a before/after reconciliation and recovery plan. |
| Approval and close | Payslip state transitions and locked historical records are implemented. | There is no evidenced unified approval workflow for invoices, payments, payroll, reconciliations or finance period close. | Assign preparer/reviewer/approver roles, segregation of duties, close checklist and evidence-retention rules. |

## Reconciliation controls

### Automated, read-only checks

Run:

```bash
php artisan database:integrity-audit
php artisan finance:reconciliation-audit
```

- `database:integrity-audit` checks configured duplicate and orphan-reference invariants, including invoice and transaction relationships.
- `finance:reconciliation-audit` checks that each payslip total equals `manual_amount + sum(employee_pay_slip_items.amount)`, that Paid payslips have `paid_on`, and that employee/month payslip periods are not duplicated (including retained soft-deleted rows).
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