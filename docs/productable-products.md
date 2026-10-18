# Automatic Products for Productable Records

## Supported models and creation

`Camp`, `Course`, `Event`, and `Workshop` are the supported productable models. Their Eloquent observer creates a related `Product` in the same database transaction as the source model, using the source `title` and an initial `draft` status. If a title is blank, the product uses a stable model-name and record-ID fallback. Product status is managed separately and is not overwritten when a source title changes.

The database enforces one product row per non-null `(productable_type, productable_id)` pair. The observer reuses an existing product on repeated lifecycle events and synchronizes the title. Soft-deleting a source soft-deletes its product; restoring the source restores and reuses the same product. Force-deleting a source also soft-deletes the product so the catalog record remains available for historical interpretation. Product records are not hard-deleted by this lifecycle.

Unsupported models are not automatically productized. Product prices remain separate records in the unified `product_prices` ledger; creating a Product does not infer or copy a price.

## Existing records and backfill

Run `php artisan products:backfill` first. The command is dry-run by default and reports missing links and duplicate groups without changing data. Review duplicate groups before migration or remediation; duplicates are deliberately not deleted or silently merged. To create missing products, run `php artisan products:backfill --apply`. Re-running the command is safe. Soft-deleted source records are reported but do not receive new products; existing products for soft-deleted sources remain inactive.

The unique-index migration stops with a list of duplicate productable pairs if duplicates already exist. Resolve those pairs explicitly before deploying the migration. The migration does not discard catalog history.
