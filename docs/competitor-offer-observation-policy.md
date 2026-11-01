# Competitor offer observation policy

**Policy version:** `v1`  
**Effective date:** 2026-10-27  
**Scope:** Manual competitor-offer observations in Management-OS.

## Record model

`CompetitorOffer` is the editable current working record. Each creation and material update is recorded by `CompetitorOfferSnapshotService` as an appended `CompetitorOfferObservation` snapshot. Snapshots preserve the title, offer type and description, price, currency, price basis, source URL, validity window, observation time, recording time, recorder, and whether a source URL was supplied.

## Provenance and time

- `observed_at` means when the offer was observed. `recorded_at` means when the application recorded the snapshot. They are not interchangeable.
- `source_url` records the public source page when known. If absent, provenance status is `unknown`; no source is inferred from the competitor's homepage.
- `currency` is explicit and nullable. Legacy currency is unknown until verified. The application does not fill it from company defaults or nearby records.
- `price_basis` distinguishes one-time, monthly, annual, per-unit, starting-at, and unknown. It is not a currency or tax classification.
- Validity dates describe the source's stated offer window when available. They are not inferred from observation date.
- Existing offers are not backfilled into fabricated historical observations by migration. The first snapshot is created only when a record is created or materially updated after deployment.

## History and comparison views

The competitor-offer detail view exposes the complete append-only observation history, including source status, source URL, observed and recorded timestamps, and recorder where available. The resource view is gated by `competitor_offer.index`; the history is not a separate permission surface. Each observation compares its price with the immediately preceding observation only when both currency and price basis match. Unknown prices, currency changes, and price-basis changes are explicitly marked as not comparable. No foreign-exchange or unit normalization is inferred.

The market-analysis dashboard continues to show a permission-gated recent-observation summary. It is a short recent-activity view, not a replacement for the full per-offer history.

## Correction behavior

A material edit appends a snapshot rather than changing earlier observations. Corrections should be made with the actual corrected observation time and source evidence where known. A snapshot is not proof that the source was accurate, current, or authorized for reuse. Soft deletion of the current offer does not imply that its observation history is a correction or deletion record.

## Reporting guardrails

Do not compare prices across currencies or price bases without an explicit normalization rule. Do not interpret missing price as zero. Do not claim market share, verified price, or competitor intent from manually entered observations alone. Source terms, access rights, collection cadence, and retention must be reviewed before automated collection or scraping.

## Quality checks

Tests cover initial snapshot creation, append-only history on material edits, and explicit unknown provenance. The current implementation is manual and does not connect external providers or Google Ads.
