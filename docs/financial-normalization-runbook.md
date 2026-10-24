# Financial Data Normalization Runbook

The `finance:normalize-data` command is a deterministic, configuration-driven backfill for the canonical fields introduced in issues #308 and #309. It never rewrites legacy source columns. It does not guess missing currency, infer integer units, calculate a vendor payable total from components, or silently change invoice/status semantics.

## Default behavior

```bash
php artisan finance:normalize-data
```

The command is a dry run unless `--apply` is explicitly provided. Dry runs do not update source records or create audit rows. The command reports record-level and field-level counts for `changed`, `unchanged`, `rejected`, and `quarantined` outcomes.

The checked-in `config/financial-normalization.php` intentionally leaves amount units unset, exact currency/classification maps empty, and invoice semantic rules unapproved. As a result, populated rows with unresolved meanings are quarantined rather than changed. This is deliberate; a successful command exit does not mean that all rows were normalized.

## Approving mappings

Before applying any mapping, update `config/financial-normalization.php` in a reviewed change and record the evidence in the migration plan. For each mapping:

- `amounts.unit` must be explicitly set to `major` (legacy integer already represents whole currency units) or `minor` (legacy integer represents minor units and is divided by 100). Do not set it based on a convenient sample. Non-integer legacy inputs and amounts exceeding the `DECIMAL(19,2)` range are rejected; negative transaction amounts are quarantined for refund/reversal classification.
- `currencies.map` is an exact stored-value-to-approved-code map. Targets must be three-letter uppercase codes, and values not present in the map are quarantined. The format check does not independently establish ISO 4217 validity; that must be established by the approved mapping. A missing source currency must not be inferred from a related account or invoice.
- `classifications.map` is an exact legacy-to-canonical map. Unknown values are quarantined; the original status/type is never overwritten.
- `invoice_semantics.rule` remains null until the invoice field meanings and total relationship have been approved. Supported checks are `customer_vat_zero` and `vendor_total_equals_components`; these only classify records and do not recalculate totals. The former still requires the applicable qualified tax review before use.
- Keep mappings scoped to the exact source table/field. Review them against the canonical financial data contract and migration plan before applying.

## Apply and audit

```bash
php artisan finance:normalize-data --apply
php artisan finance:normalize-data --apply --batch=100
```

Batch size must be between 1 and 1000. The command uses stable primary-key batches and a transaction per source record. If a row has any rejected or quarantined field, the entire row is left unchanged; no partial field update is applied. Existing canonical values are never overwritten if the configured mapping disagrees with them.

Every apply run is recorded in `financial_normalization_runs`. Per-field outcomes, record-level outcomes, source-value snapshots, canonical candidates, reasons and source record references are written to `financial_normalization_items` in the same transaction as any canonical update. After an apply run, expected canonical totals are compared with persisted totals by table, field, verified currency and canonical status/type (or `NOT_APPLICABLE` where no classification applies); rows without verified currency/classification are excluded and counted as unresolved. Audit records are internal operational evidence and must not be copied into ordinary logs or test fixtures. Source columns and timestamps remain unchanged. Soft-deleted rows are included in the normalization scope and are not restored.

A failed run is marked `failed`; already committed records and audit rows remain available. Correct the cause and rerun with the same approved mapping. Existing canonical values make the rerun idempotent. Review each run's record/field summary and all quarantined/rejected outcomes before treating it as complete. Do not switch reports or writers to canonical fields until reconciliation and applicable approvals are recorded.

## Reconciliation checklist

- Compare counts by source table and outcome, including soft-deleted records.
- Compare pre/post aggregate totals by table, period, approved currency and status without combining currencies.
- Review every rejected/quarantined category and canonical-value conflict.
- Confirm source values and original source columns remain unchanged.
- Confirm repeat runs report already-mapped records as unchanged and do not create duplicate effects.
- Keep a recovery/forward-fix plan. The audit history is intentionally protected from migration rollback once a normalization run exists.

The command does not authorize a production run, accounting/tax interpretation, automatic FX, or historical conversion beyond the mappings explicitly reviewed and approved for that environment.
