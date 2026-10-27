# Financial reconciliation reporting: current-state audit v1

**Status:** Engineering scope audit; not a settlement or accounting approval  
**Date:** 2026-10-27  
**Roadmap:** [Issue #352](https://github.com/deputy-proxy/Management-OS/issues/352)

## Existing capability

The repository already provides `php artisan finance:reconciliation-audit`, implemented by `App\\Console\\Commands\\AuditFinanceReconciliation`, with feature coverage in `tests/Feature/FinanceReconciliationAuditTest.php`. The documented scope is read-only: it reports configured finance and payroll invariants and does not repair records. The related normalization runbook documents findings such as unresolved canonical amount/classification values, decimal precision, currency-code format, transaction/account and balance/account currency consistency, negative transaction values, invoice semantic rules where explicitly approved, and payroll reconciliation invariants.

Payment-allocation documentation also describes a read-only check for payment-side totals and invoice-currency consistency; proposed and reversed allocations are excluded from active totals. Invoice-linked transaction sums are informational, not proof of settlement.

These are diagnostics and partial reconciliation controls, not a complete reconciliation workbench across bank statements, transactions, invoices, payment allocations, and account balances.

## Important boundaries

- A clean audit means no configured finding was detected. It does not prove a bank statement was imported completely, that a transaction settled an invoice, or that a financial amount is correctly interpreted under accounting policy.
- A three-letter uppercase currency check validates format only; it does not prove the code is a valid currency or that the amount is denominated in that currency.
- Stored account balance snapshots are reported inputs, not balances calculated from a reconciled ledger.
- Legacy integer amounts and their units remain unresolved unless an exact source-backed mapping has been approved. Do not compare or aggregate them as though their units were established.
- No automatic matching, repair, duplicate deletion, or status transition should be added to the read-only audit without a separate reviewed contract.

## Gaps before calling this a reconciliation report

1. **Coverage inventory:** enumerate the source tables and joins each diagnostic checks, including soft-deleted records and orphan references.
2. **Exception contract:** give each finding a stable code, severity, affected source type, count, limited record references, and actionable remediation guidance. Avoid logging monetary values or sensitive invoice data.
3. **Traceability:** report how an exception links to its underlying records and which source facts produced the finding.
4. **Duplicate and stale records:** define evidence-based duplicate/staleness rules before presenting a row as a duplicate or stale; never auto-resolve uncertain matches.
5. **Allocation and settlement:** distinguish proposed, confirmed, reversed, and over-allocated payment allocations from invoice settlement. Reconciliation status must not infer paid status from amount similarity alone.
6. **Multi-currency:** report separately by confirmed currency and explicitly surface missing/invalid currency. Do not apply FX or infer currency from account metadata without an approved rule.
7. **Ownership and closure:** assign a responsible reviewer, status, resolution note, evidence reference, and closure timestamp for each exception. Keep the audit command read-only.
8. **Independent verification:** test the reported exception counts against direct source queries and fixtures, including balanced/unbalanced, missing, duplicate, stale, orphaned, reversed, and multi-currency cases where supported.

## Recommended staged delivery

### Stage A: evidence and reporting contract

- Map every existing check to source tables, conditions, exclusions, and current test coverage.
- Define stable finding codes, severity, safe sample references, permission rules, and reviewer ownership.
- Document which results are informational versus blocking.
- Capture a baseline from a representative non-production database and reconcile counts against independent queries.

### Stage B: report surface

Only after Stage A is reviewed, expose a permission-protected read-only report that groups findings by category and severity, allows authorized drill-down to source records, and clearly displays audit timestamp and scope. Preserve the existing CLI command and its non-mutating behavior.

### Stage C: resolution workflow

If the business needs resolution tracking, implement it separately from the diagnostic calculation. Record reviewer, resolution status, rationale, evidence, and timestamps. Any source-record correction must use the source domain's existing authorization and audit mechanisms, never a silent reconciliation auto-fix.

## Decisions still required

- Which finding codes and severities block operational close versus require review only?
- Who owns each discrepancy category and approves closure?
- What is the approved source of truth for account balances and statement completeness?
- Which payment-allocation and invoice states count as confirmed, and which remain provisional?
- What retention and access controls apply to reconciliation evidence?
- Which source-backed monetary and currency mappings are approved for each environment?

Accounting and finance owners must approve these decisions before the report is described as proving settlement, cash balance, payable/receivable position, or accounting correctness.

## Acceptance status

This audit documents the known diagnostic boundary and a staged path to issue #352. It does not add a new reporting UI, mutate financial data, claim complete reconciliation coverage, or substitute engineering assumptions for finance approval.
