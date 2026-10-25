# Manual bank statement matching

## Scope and current behavior

The Finance cluster's **Statement Matching** screen exposes rows from completed bank statement imports. Each row retains its import batch, financial account, source file SHA-256, parser version, source page and source row. Imported rows are immutable; the matching screen records a decision against an existing record and does not create or post ledger transactions.

Supported targets are existing transactions, customer invoices and vendor invoices. Supported outcomes are `exact_match`, `partial_candidate`, `unmatched`, `ambiguous` and `rejected`. Exact and partial decisions require a target; the other outcomes clear the target. A reason is required for every decision. Re-recording a decision is an override, not an update-in-place: prior decisions remain in the activity log with actor, timestamp, reason and previous decision fields.

## State and controls

- Only rows whose import batch is `completed` can receive decisions.
- `transaction.update` is checked server-side for every decision. Screen visibility is not the authorization boundary.
- A transaction target must belong to the same financial account as the imported row.
- An exact transaction match must have the same amount and currency when the existing transaction has those canonical values.
- One imported row cannot make two active exact matches to the same target. A later decision can reverse the prior match without deleting its history.
- Partial candidates and ambiguous outcomes are not proof of settlement. They remain review decisions only.
- Invoice matching records a link candidate only. It does not mark an invoice paid, create allocations, change balances, or settle anything.
- Matching records `settlement_performed`, `allocation_performed` and `ledger_mutation_performed` as false in the audit event.

## Correction and reversal

To correct a decision, record a new decision with an explanatory reason. Use `unmatched`, `ambiguous` or `rejected` to clear the current target, or record a replacement candidate. The activity history is append-only; do not delete prior decisions to make the current view look tidy.

## Domain boundaries and reporting

The workflow currently affects only the matching-decision audit trail. It does not alter transaction totals, invoice balances, payment allocations, settlement state, or accounting reports. Imported amounts and currency are read-only. No Romanian legal, tax, invoicing, or accounting rule is asserted by this interface. Production accounting treatment and any future allocation/settlement workflow require domain-owner review before implementation.

## Review checklist

- [ ] Finance/domain owner confirms target semantics and acceptable exact-match criteria.
- [ ] Reviewer confirms whether invoice matches need a separate payment/allocation workflow.
- [ ] Confirm production MySQL behavior and permissions in staging before release.
- [ ] Test full, partial, unmatched, duplicate-target, invalid-target, unauthorized and correction/reversal cases.
