# Receivables and payables reporting: current-state audit v1

**Status:** Engineering scope audit; invoice balance semantics remain gated  
**Date:** 2026-10-27  
**Roadmap:** [Issue #350](https://github.com/deputy-proxy/Management-OS/issues/350)

## Existing capability

The finance overview currently reports customer and vendor invoice status counts, not outstanding balances. Customer invoices do not have a currency column and their legacy amount units are undocumented. Vendor invoices store currency but use legacy integer amount fields and free-text status. The existing dashboard intentionally does not aggregate invoice amounts into receivables/payables.

The payment allocation workflow provides a separate, read-only projection of source payment amount, confirmed invoice allocations, fees, unallocated remainder, and allocation lifecycle. Proposals are not counted as confirmed allocations; reversed allocations leave active totals while their history remains. The workflow does not modify invoice status or invoice balances.

The allocation contract explicitly leaves canonical customer/vendor invoice totals unresolved. Until those totals and correction rules are approved, confirmed payment allocations establish reviewed payment-side allocation facts, not an invoice's remaining balance or paid status.

## Reporting gaps and invariants

1. **Invoice-side balance formula is not yet approved.** Do not calculate outstanding balance as invoice amount minus allocations while the canonical invoice total, amount unit, credits, corrections, and relevant statuses remain unresolved.
2. **Statuses are not a controlled vocabulary.** Existing invoice status is free text. Preserve unknown values and define a reviewed mapping before using status to include/exclude an invoice or infer that it is open, overdue, or paid.
3. **Payment allocation state is not settlement state.** Only confirmed allocations count as active payment allocations. A proposed or reversed allocation must not be presented as settled payment; an allocation total or amount match alone does not mark an invoice paid.
4. **Currency support differs by invoice type.** Customer invoices lack a currency field; vendor invoices have one. Require verified invoice currency matching the source payment currency before any invoice-side monetary aggregation. Do not infer missing customer currency from account, location, or payment.
5. **Legacy amount units remain unresolved.** Do not scale integer invoice/transaction fields, combine them with canonical decimal amounts, or compare totals across those representations without an exact source-backed mapping and approval.
6. **Partial payments and credits need explicit rules.** The company-owner decision register currently describes one full-outstanding-balance payment as the approved invoice workflow and says multiple partial payments are not approved. Credit notes, adjustments, refunds, reversals, overpayments, and exceptions still require explicit source and correction contracts before being reflected in balances.
7. **Aging needs a defensible date basis.** Define which approved invoice due date and reporting cutoff determine age, the Europe/Bucharest timezone boundary, and how missing/invalid due dates are surfaced. Invoice month, issue date, due date, and payment date are not interchangeable.
8. **Traceability and permissions matter.** Each aggregate must drill down only through existing authorized record routes and explain the included invoice, allocation, and credit source records. Do not expose record-level data through an aggregate-only dashboard path.
9. **Exclusions must be visible.** Missing currency, unknown status, unresolved total semantics, invalid invoice links, and unrecognized adjustment types should appear as reason-coded exceptions, not be silently dropped or treated as zero.

## Safe staged delivery

### Stage A: approved invoice contract

- Define canonical gross/total semantics for customer and vendor invoices, including amount units, currency, rounding, credits, corrections, and source references.
- Define allowed status values and authorized transitions, including explicit settlement confirmation and historical status mapping.
- Reconcile the contract against representative source documents and specialist-reviewed examples where accounting treatment applies.
- Confirm whether the existing no-partial-payment policy remains in force and how exceptions, credits, and reversals should work.

### Stage B: balance and aging calculation

- Calculate outstanding balances only from approved invoice totals and confirmed eligible allocations/credits.
- Define deterministic handling of invoice revisions, reversals, credit notes, and unmatched or over-allocated payments.
- Define aging buckets, due-date semantics, reporting cutoff, currency grouping, and missing-data behavior.
- Add service and feature tests for paid/unpaid, due/overdue, missing dates/currency, credits, reversed/proposed allocations, duplicate source events, unsupported statuses, multiple currencies, and boundary dates.
- Independently reconcile report results to the included source records.

### Stage C: report surface

- Separate receivables and payables, with one currency per total and clearly labeled reporting date/basis.
- Display outstanding, overdue, not-yet-due, and unresolved amounts only where their inputs meet the approved contract.
- Provide permission-protected drill-down, visible exclusions, and report timestamp.
- Preserve current dashboard behavior until a separately reviewed change is validated.

## Decisions and review gates

The company-owner decision register records payment allocation and status principles, but it does not by itself establish statutory accounting/tax approval. The payment allocation documentation explicitly says invoice-side balance validation remains unresolved until canonical invoice totals and correction rules are approved.

Do not implement invoice-side balance or aging totals from legacy integer amount/VAT columns, free-text status, or transaction links alone. Record the approved source mapping, status vocabulary, correction rules, and review evidence first.

## Acceptance status

This audit distinguishes the existing payment-side allocation controls from the missing invoice-side receivable/payable contract. It does not calculate balances, infer settlement, alter invoice statuses, or claim a reconciled aging report.
