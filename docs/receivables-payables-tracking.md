# Receivables and payables tracking

**Issue:** #350  
**Status:** Operational AR projection for supported issued customer invoices; vendor payable totals and legacy invoice amounts remain explicitly unresolved.

## Reporting contract

The ReceivablesPayablesData service is a read-only projection as of a caller-supplied date in Europe/Bucharest. It does not mutate invoice status, payment transactions or payment-allocation history.

### Supported receivables

A customer receivable balance is calculated only when all of the following are true:

- The document is an immutable issued invoice snapshot with a non-empty snapshot hash and document_status=issued.
- gross_total is present and positive, and currency_code is a valid three-letter uppercase code.
- The issued invoice has a linked legacy customer-invoice allocation target.
- The legacy target exists, is not soft-deleted, and its explicitly stored canonical currency matches the issued snapshot currency.
- Every confirmed allocation has the same currency, a canonical money_in source transaction, a positive canonical payment amount, and no duplicate active allocation or source-payment over-allocation.

Only confirmed invoice allocations are subtracted. Proposed, reversed, and rejected allocations remain visible in the history but do not reduce the balance. Multiple confirmed allocations from distinct transactions are reported as partial allocation. A duplicate confirmed allocation from the same source transaction to the same invoice makes that invoice unresolved.

The report uses exact decimal arithmetic. It reports outstanding and credit amounts separately. If confirmed allocations exceed the issued gross total, the excess is shown as a credit balance rather than clamped or hidden. A zero outstanding balance is fully_allocated_pending_settlement_confirmation, not paid. No invoice status is changed.

### Aging

The reporting cutoff is the selected date in Europe/Bucharest. Aging uses only the canonical issued snapshot due_date:

- not_due: due date after the reporting date.
- due_today: due date equals the reporting date.
- overdue_1_30_days, overdue_31_60_days, overdue_61_90_days, and overdue_over_90_days.
- due_date_unknown or due_date_invalid when the source does not support an age.

Fully allocated and credit-balance invoices have no outstanding aging bucket. Aging is not inferred from invoice month, payment date, or free-text invoice status.

### Explicitly unresolved records

- Legacy customer invoices not linked to an issued snapshot are visible without monetary balances because their amount units, currency and status mapping remain unapproved.
- Corrected issued invoices and credit/debit notes are visible as unresolved. The report does not net correction documents until a reviewed correction/credit rule exists.
- Vendor invoice records are visible with contractor workflow status, payable_on aging and confirmed allocation facts. The canonical payable total is not approved in config/financial-normalization.php, so no vendor outstanding amount is calculated.
- A vendor allocation amount is a confirmed payment-allocation fact only; it is not evidence that the payable is settled.
- Missing links, currency conflicts, duplicate active allocation rows, invalid payment direction, deleted source transactions and source-payment over-allocation are reason-coded and excluded from monetary balances. The report does not guess duplicate bank transactions without a stable source identity; those remain a reconciliation-review task.

## Audit and authorization

The page requires receivables-payables.view. Customer invoice, vendor invoice and transaction drill-down links are rendered only when the current user also has the corresponding resource index permission. Payment-allocation proposal, confirmation, rejection and reversal remain controlled by PaymentAllocationService and transaction.update; the model activity log and explicit activity events preserve actors, reasons and timestamps. The report itself performs no writes.

## Policy boundaries and deferred validation

The company-owner decision register describes a one-full-balance-payment workflow and does not approve multiple partial payments. Existing payment-allocation records can contain multiple confirmed payment facts; the report surfaces partial allocation as an operational balance state without changing invoice status or representing it as an approved statutory settlement. Confirm the policy exception with Finance before production use.

Independent reconciliation against real invoice/payable records and finance-owner/accountant approval remain deferred per the issue development-priority comment. No legacy amount unit, currency, tax treatment, FX rate, or vendor payable total is inferred.
