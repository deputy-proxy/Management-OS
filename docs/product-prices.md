# Product Prices consolidation

## Unified record

`product_prices` is the consolidated price ledger. Each record has a polymorphic `priceable` owner, an amount (`price`), optional currency code (`currency`), a `price_type` (currently `standard`), and optional inclusive validity dates (`valid_from`, `valid_until`). Existing records retain their stored numeric amount; this migration does not apply tax, discounts, currency conversion, or rounding rules.

Currency is nullable because the old Course Price and Camp schemas did not reliably store currency. The migration intentionally does not guess a currency. Event and Workshop currency values are copied verbatim and should be normalized separately only with an explicit business decision.

## Backfill mapping

| Legacy source | Owner | Amount | Currency | Validity |
| --- | --- | --- | --- | --- |
| `course_prices` | Course (`course`) via `course_id` | `amount` → `price` | unknown (`NULL`) | `start_on` / `end_on` |
| `event_prices` | Event (`event`) via `event_id` | `amount` → `price` | copied verbatim | none in source |
| `workshop_prices` | Workshop (`workshop`) via `workshop_id` | `amount` → `price` | copied verbatim | `start_on` / `end_on` |
| `camps.camp_price` | Camp (`camp`) via `id` | `camp_price` → `price` | unknown (`NULL`) | `start_on` / `end_on` |

`legacy_source_type` and `legacy_source_id` identify each copied source row and are unique together. Soft-delete state and timestamps are preserved. Existing Event and Workshop foreign-key columns are remapped to the new unified row IDs and restored by rollback. Legacy tables remain in place as rollback/audit sources; the migration copies rather than removes data. Existing `product_prices` records without a legacy source are left untouched.

## Compatibility and lookup

The existing Course Price, Event Price, and Workshop Price model names remain as compatibility adapters, but their reads and writes now target `product_prices`; their Filament resources therefore share the unified source of truth. Camp create/edit screens also read and write a related Product Price. The old tables remain untouched as rollback/audit sources and are not used for new model writes.

`ProductPriceService::effectivePrice($owner, $date)` returns the effective record with the latest `valid_from` (then latest ID) whose optional bounds include the requested date. `ProductPrice::effectiveAt($date)` exposes the matching query scope. No active record is returned if no price covers the date.

The old schemas do not define tax, discount, or a consistent price-type policy, so the unified record currently uses `standard` without changing any amount. Currency is preserved verbatim where present and remains nullable where the source never recorded it; no currency conversion or rounding is performed.