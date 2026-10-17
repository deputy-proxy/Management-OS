# Products Overview

The Products Overview is available from the Overview dashboard to users with both `products-overview.view` and `product.index`. It summarizes non-soft-deleted `products` and offers filters for the stored product status, productable type, and price readiness. Resource links are rendered only when the viewer has the corresponding list permission.

## Product source of truth

`products.title` and `products.status` are the catalog title and stored lifecycle/status value. The polymorphic `productable_type` / `productable_id` pair links to the source record. The overview supports the current productable types `camp`, `course`, `event`, and `workshop`. A missing or unsupported linked record is reported and does not cause a synthetic title, price, or availability value to be generated.

The database currently stores status as a string without a controlled lifecycle vocabulary. The overview therefore shows and filters the exact stored value, rather than inferring that a particular value means active, published, or sellable.

## Price source of truth

`product_prices` is the consolidated price ledger documented in [Product Prices](product-prices.md). A price belongs to the linked productable record through `priceable_type` / `priceable_id`; it is not stored on the Product row. Only non-soft-deleted price records are considered. An effective price has optional `valid_from` and `valid_until` bounds that include the current application-local date.

- Exactly one effective price: show its stored amount and currency.
- No effective price: show “No effective price”, including when the only prices are future-dated or expired.
- More than one effective price: show an ambiguous state and no amount. The dashboard does not guess which overlapping price should win.
- Missing linked productable: mark the record unavailable and do not display a price.

Amounts are shown as stored with two decimal places. The overview does not convert currencies, apply taxes or discounts, or derive prices from legacy columns. Currency is displayed as stored; if absent, it is marked unspecified.

## Availability

Availability is a date-range indicator, not a product lifecycle state. Camps use `start_on` / `end_on`; Events and Workshops use `start_date` / `end_date`. The range is inclusive: before the start is “Upcoming”, after the end is “Ended”, and dates within the range are “In date range”. Course has no equivalent date range in its current model, so availability is reported as “Not defined by model”. The product's stored status is never substituted for availability.

## Scope and limitations

The metrics cover products that already exist. Automatic product creation for all eligible source records belongs to issue #172 and is not performed by this read-only overview. The page does not change product or price records. Its current catalog is loaded as a collection and filters are applied in the overview service; if the catalog grows substantially, pagination and database-side filtering should be considered before expanding the dataset.
