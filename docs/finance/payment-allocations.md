# Payment allocation and reconciliation controls

## Entities and lifecycle

`transactions` are the source payment records. `payment_allocations` records an amount assigned to a customer invoice, a vendor invoice, or a separately identified payment fee. An allocation proposal is not counted in payment totals until confirmed. Confirmed rows can be reversed, with the original row, reversal reason, actor, timestamp, and activity events retained.

Lifecycle: `proposed -> confirmed -> reversed`, or `proposed -> rejected`. The UI does not permit editing or deleting allocation history. Each proposal and transition requires `transaction.update`. Allocation summaries are read-only projections over confirmed allocations.

## Enforced invariants

- Allocations require a positive decimal amount and a reason. Invoice allocations have exactly one invoice target; fee allocations have none.
- Source payment must have a verified canonical amount and ISO-style three-letter currency. Legacy integer amounts are not guessed or converted.
- Allocation currency must match the source payment currency. Foreign exchange allocation is not supported.
- Invoice allocations require an approved canonical direction: `money_in` may target customer invoices and `money_out` may target vendor invoices. Unknown classifications are blocked. An invoice target must have a verified canonical currency matching the payment currency.
- Confirmation locks the source transaction and checks the sum of all confirmed invoice allocations plus fee allocations. The total cannot exceed the absolute canonical payment amount.
- Proposals do not consume payment capacity. Reversed allocations no longer count in active totals, and reversal events preserve the correction history.
- Excess cash remains explicitly unallocated in the amount summary. An entirely unmatched payment is labelled `unmatched`; a pending proposal is labelled `proposed` but is not counted as allocated. Fees are tracked separately from invoice allocations and represent a carve-out of the source transaction amount, not an additional amount layered on top of it. The source transaction and invoice status/balances are never modified by this workflow.

## Reconciliation status

For each source transaction, the summary reports the canonical payment amount, confirmed invoice allocations, confirmed fees, total allocated, unallocated remainder, and one of `needs_review`, `unmatched`, `proposed`, `partially_allocated`, `fully_allocated`, or `overallocated`. An unmatched payment has no confirmed or pending proposal; a proposed payment has at least one proposal awaiting review. `overallocated` is a detected integrity problem; the service prevents new confirmations from creating it.

Payment-side totals and confirmed invoice-currency consistency are checked with a read-only audit by `php artisan finance:reconciliation-audit`. Proposed and reversed allocations are excluded from active totals.

## Explicit unresolved invoice-side rules

The current finance normalization configuration leaves invoice semantics unresolved for both `customer_invoices` and `vendor_invoices`. In particular, it does not approve a canonical payable/receivable total for both invoice types. Therefore, the application does **not** infer an invoice's remaining balance, mark an invoice paid, or claim invoice-side reconciliation from legacy amount/VAT columns. Confirmed invoice allocations show reviewed amounts and target references, but the summary flags invoice balance validation as `unresolved_invoice_total_semantics` until a qualified finance/accounting owner approves the canonical totals and correction rules.

No legal or Romanian accounting requirement is inferred from the current schema. FX, fees that change invoice totals, overpayment application, and invoice-side capacity checks need an approved domain contract before being automated.

## Corrections and reporting impact

Reject an unconfirmed proposal with a required reason. Reverse a confirmed allocation with a required reason; do not delete it. Create a new proposal for a corrected target or amount. A reversal does not mutate the original transaction or invoice. This feature affects only the allocation subledger and its reconciliation summary; it does not post general-ledger entries or change invoice/payment lifecycle fields.

## Test evidence and reviewer sign-off

Synthetic tests cover partial allocation, proposed versus confirmed state, payment over-allocation rejection, separate fees, reversal history, invalid/missing canonical values, and currency mismatch. Independent reconciliation uses the source payment amount and compares it against the sum of confirmed allocations and fees.

- Finance/accounting reviewer: **pending**.
- Approved invoice total semantics for customer and vendor invoices: **unresolved**.
- Foreign-exchange policy: **not supported; unresolved**.
- Production rollout: run the migration and `finance:reconciliation-audit` in staging, inspect findings against source evidence, and obtain reviewer sign-off before using the feature for operational accounting.
