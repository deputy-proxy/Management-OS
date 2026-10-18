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

## Runtime model and lookup

`ProductPrice` is the only runtime price model. The unified `ProductPriceResource` manages prices for courses, events, workshops, and camps. Event and Workshop forms no longer write legacy singular price foreign keys. Legacy morph-map aliases (`course_price`, `event_price`, `workshop_price`) resolve to `ProductPrice` so historical comment/activity references continue to resolve without retaining adapter classes. The old source tables remain untouched as rollback/audit sources and are not used for new model writes.

`ProductPriceService::effectivePrice($owner, $date)` returns the effective record with the latest `valid_from` (then latest ID) whose optional bounds include the requested date. `ProductPrice::effectiveAt($date)` exposes the matching query scope. No active record is returned if no price covers the date.

The old schemas do not define tax, discount, or a consistent price-type policy, so the unified record currently uses `standard` without changing any amount. Currency is preserved verbatim where present and remains nullable where the source never recorded it; no currency conversion or rounding is performed.

## Runtime-reference audit

The three legacy adapter models, their separate Filament resources, factories, and seeders have been removed. Runtime price creation and editing now use `ProductPrice` and `ProductPriceResource`. Event and Workshop retain their canonical `productPrices()` polymorphic relationships. The historical source tables and legacy owner foreign-key columns are retained for migration rollback/audit compatibility, but are no longer exposed by runtime model relationships or forms.

### Migration-only storage and rollback contract

- `2026_10_17_160000_unify_legacy_product_prices.php` copies `course_prices`, `event_prices`, and `workshop_prices` into `product_prices`, preserving amount, source currency where known, validity dates where present, timestamps, soft-delete state, and source provenance.
- The migration also backfills Camp prices with unknown currency represented as `NULL` rather than guessed.
- `events.event_price_id` and `workshops.workshop_price_id` are remapped to canonical row IDs during `up()` and restored to original legacy IDs during `down()`.
- The old `course_prices`, `event_prices`, and `workshop_prices` tables remain as migration rollback/audit sources. Do not rewrite or delete the historical migrations or drop these tables in this change.
- `ProductPriceConsolidationTest` verifies source-row preservation, owner IDs, validity bounds, soft-delete state, and migration rollback. It also verifies that Course, Event, and Workshop prices are created through their canonical `productPrices()` relationships.

### Retirement outcome

`ProductPrice` is the only runtime price model. Do not rewrite historical migration files or drop the legacy source tables as part of application cleanup. The legacy morph aliases are deliberately retained and point to `ProductPrice` to preserve resolution of historical polymorphic records.
