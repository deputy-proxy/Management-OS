# Transaction classification v1

**Policy version:** `v1`  
**Effective date:** 2026-10-27  
**Scope:** Direction-aware classification coverage for operational transaction reporting. This policy does not define a general ledger, settlement proof, statutory tax treatment, or a monetary-unit migration.

## Canonical direction

New transactions entered through the transaction form must specify `transaction_type_code` as `money_in` or `money_out`. The existing `transaction_type` remains the legacy relationship/type label (`Customer`, `Vendor`, `Tax`, or `Other`). The two fields answer different questions and must not be conflated.

| Legacy type label | Explicit direction | Reporting classification | Interpretation limit |
| --- | --- | --- | --- |
| Customer | `money_in` | `customer_money_in` | Customer-related money movement; not proof of invoice settlement or revenue recognition. |
| Customer | `money_out` | `customer_money_out` | Does not distinguish refund, reversal, fee, or correction. |
| Vendor | `money_in` | `vendor_money_in` | Does not distinguish refund, rebate, or correction. |
| Vendor | `money_out` | `vendor_money_out` | Vendor-related money movement; not proof of payable settlement or expense recognition. |
| Tax | `money_in` / `money_out` | `tax_money_in` / `tax_money_out` | Does not calculate statutory tax or prove an obligation was paid. |
| Other | `money_in` / `money_out` | `other_money_in` / `other_money_out` | Requires a reviewed subtype if reporting needs more detail. |
| Missing/unsupported type or missing/unsupported direction | Any | `unclassified_legacy` | Remains visible as unresolved; never infer direction from amount sign, linked account, invoice, or name. |

## Reporting contract

- The finance dashboard reports transaction counts by classification for current and prior month. Existing amount summaries remain unchanged because the units of legacy integer `amount` fields are not established.
- Classification coverage includes non-deleted transaction rows, even if their account link is invalid; amount summaries continue to use the existing linked-account join. Reconcile orphan references separately.
- Legacy rows with null `transaction_type_code` remain `unclassified_legacy`. No backfill is performed by this change.
- The classification describes only the combination of the existing relationship/type label and explicit money direction. It does **not** establish revenue, expense, receivable, payable, tax liability, cash balance, transfer, or settlement semantics. Those require separate, reviewed rules tied to source evidence.
- The canonical direction field accepts only `money_in` and `money_out` for this v1 form. Transfers, cross-currency transfer legs, fees attached to a transfer, linked refunds, and reversals require explicit related-event, counterparty amount/currency, idempotency, and correction contracts before they can be posted as distinct workflows.
- A classification is not settlement evidence. A transaction-to-invoice link, transaction count, amount match, or stored invoice status alone does not mark an invoice paid.
- No amount scaling, currency inference, invoice-total recalculation, tax calculation, or historical status rewrite is performed.
- Results are reproducible from the persisted type and direction values plus this versioned mapping. The service does not mutate source transactions or create a classification audit event; source edits remain subject to existing transaction permissions and activity logging.

## Validation and audit

Automated tests cover all eight supported type/direction combinations, case/whitespace normalization, unsupported legacy types, missing or unsupported direction, and unresolved output. The transaction model rejects unsupported non-null direction values. Activity logging retains both legacy type and canonical direction. Future mapping of legacy rows must be a separate reviewed normalization run with source values, mapped values, rationale, and an exception list; do not populate `config/financial-normalization.php` from the old labels alone.

This is an operational classification contract, not a declaration that finance/accounting policy has been specialist-approved. Revenue/expense recognition, receivable/payable balances, tax treatment, and regulatory reporting remain unresolved until their definitions and sample transactions are approved by the responsible finance/accounting reviewer.

## Next linked-event work

Payment allocation and reconciliation remain separate. A future transfer workflow needs paired legs and both currencies/amounts where applicable. Refund/reversal workflows need links to the original event, reason, approval actor, idempotency key, and explicit effective date. These must not be represented as a direction-only code.
