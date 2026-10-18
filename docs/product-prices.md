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

## Verified runtime-reference inventory

The compatibility adapters are still active runtime code. They all query and write the canonical `product_prices` table; they do not write to the old source tables.

| Compatibility surface | Verified callers | Why it cannot be removed yet |
| --- | --- | --- |
| `CoursePrice` | `CoursePriceResource` and its form/table/infolist; `CoursePriceFactory`; `CoursePriceSeeder`; the `course_price` enforced morph-map alias in `AppServiceProvider`; consolidation regression tests | The Filament resource and seed/test setup still depend on the legacy model's `course_id`, `amount`, `start_on`, and `end_on` accessors. The morph alias may identify historical comments or activity records. |
| `EventPrice` | `Event::eventPrice()` via `events.event_price_id`; `EventPriceResource` and its schemas; `EventPriceFactory`; `EventPriceSeeder`; the `event_price` morph-map alias; consolidation regression tests | The migration remaps the legacy `event_price_id` to the canonical row ID and its `down()` method restores the old ID. The current Filament form/infolist still exposes that relationship, and the morph alias may identify historical comments/activity records. |
| `WorkshopPrice` | `Workshop::workshopPrice()` via `workshops.workshop_price_id`; `WorkshopPriceResource` and its schemas; `WorkshopPriceFactory`; `WorkshopPriceSeeder`; the `workshop_price` morph-map alias; consolidation regression tests | The migration remaps the legacy `workshop_price_id` to the canonical row ID and its `down()` method restores the old ID. The current Filament form/infolist still exposes that relationship, and the morph alias may identify historical comments/activity records. |
| `ProductPrice` | `ProductPriceService::effectivePrice()`; `Course`, `Event`, `Workshop`, and `Camp` `productPrices` relationships; the Camp price workflow | This is the canonical price record and must remain the source of truth. |

`Event` and `Workshop` expose both the canonical `productPrices()` polymorphic relationship and a legacy singular `eventPrice()` / `workshopPrice()` relationship. Those singular relationships are still consumed by Filament forms/infolists and depend on the legacy foreign-key columns. `Course` already uses `productPrices()` for canonical price lookup.

### Migration-only storage and rollback contract

- `2026_10_17_160000_unify_legacy_product_prices.php` copies `course_prices`, `event_prices`, and `workshop_prices` into `product_prices`, preserving amount, source currency where known, validity dates where present, timestamps, soft-delete state, and source provenance.
- The migration also backfills Camp prices with unknown currency represented as `NULL` rather than guessed.
- `events.event_price_id` and `workshops.workshop_price_id` are remapped to canonical row IDs during `up()` and restored to original legacy IDs during `down()`.
- The old `course_prices`, `event_prices`, and `workshop_prices` tables remain as migration rollback/audit sources. Do not rewrite or delete the historical migrations or drop these tables in this change.
- `ProductPriceConsolidationTest` verifies source-row preservation, owner IDs, validity bounds, soft-delete state, and migration rollback. It also verifies that the compatibility adapters write to `product_prices` and that Event/Workshop singular relationships still resolve the corresponding canonical rows.

### Retirement decision

No adapter class is safe to delete in this issue: each has active Filament, factory/seeder, relationship, or morph-map references. The adapters remain intentionally as a compatibility boundary, not as a second storage ledger. No legacy source table is read for new runtime writes; references to those tables are limited to migration/rollback code and regression coverage.

A future retirement change must first migrate the relevant Filament resources/forms/infolists and factory/seeder callers to `ProductPrice`, preserve comment/activity morph identities, remove the legacy singular owner relationships and foreign-key fields only through a verified migration, and add data reconciliation plus a tested recovery plan. It must not delete historical migration files or discard provenance, currencies, validity dates, soft-delete state, or original source IDs.
