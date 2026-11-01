# Product and service opportunity analysis

## Purpose and evidence boundary

The opportunity analysis evaluates existing `Product` records and their supported productable types (`course`, `camp`, `event`, and `workshop`) against an explicitly configured scoring profile. It reads stored `MarketTrend` observations and `CompetitorOffer` history only. It does not call live providers, infer market share, forecast demand, convert currencies, or claim that a product is a validated market opportunity.

The page is available under Marketing > Product & Service Opportunities. Viewing requires `market_opportunity.view`. Product links require `product.index`; trend and offer evidence is independently gated by `market_trend.index` and `competitor_offer.index`. Scoring profile configuration requires `market_opportunity.configure`.

## Scoring profiles

A profile has a name, positive version number, lifecycle state (`draft`, `active`, or `retired`), provisional flag, description, and one or more explicit factors. A factor defines:

- a unique stable key and display label;
- evidence source (`market_trend` or `competitor_offer_match`);
- positive integer weight from 1 to 1000;
- preferred direction (`higher` or `lower`);
- minimum and maximum numeric values that map to the score range 0–100;
- source-specific metric/scope or exact-match rules; and
- optional maximum evidence age (required for competitor-offer factors).

An activated profile version (active or retired) is immutable and retained as history. Create and activate a higher version to change factors or weights; activating it retires the previously active profile. The application does not seed an active scoring profile or invent default weights and thresholds. Configuration remains provisional until its owner validates the rubric.

For a known factor value, the service linearly maps the configured minimum/maximum range to 0–100, clamped at either endpoint. `higher` maps the minimum to 0 and maximum to 100; `lower` reverses that mapping. The overall score is the weighted average of known factor scores. Missing, inaccessible, undated, or stale inputs are unknown and excluded from both numerator and denominator, never converted to zero. Any unknown or unverified factor, or a provisional profile, marks the result provisional. No known factor values yields an unknown overall score.

## Supported evidence

### Market trend

A factor selects one metric key and an explicit market. Segment is either an exact configured segment or null. The service uses the latest active matching observation. Missing values, stale observations, and observations without a freshness state or configured age limit remain unknown. Verification, confidence, source URL/reference, observation time, and freshness are included in the explanation. An unverified observation can contribute a numeric factor score but makes the overall result provisional.

### Competitor offer

A factor uses only offers whose `our_similar_offer` note exactly matches the internal product title (case-insensitive, trimmed). This is deliberately conservative; free-text similarity is not treated as product equivalence. A maximum evidence age is required. Only offers with an explicit source observation time (`observed_at` or `last_check`), a current historical snapshot, and a provided source URL count toward the factor value; the snapshot recorder’s fallback record time is not treated as an observation time. Source presence is not source verification, so this factor is labelled unverified and keeps the overall score provisional. The analysis does not compare price, currency, price basis, geography, or terms.

## Filtering, comparison, and reproducibility

The analysis page supports title search, productable type, and score-state filters. Rows are sorted by score descending, then title and product ID for deterministic ties. Expanding a row shows the profile version, configured weight and range, factor value and score, missing/stale explanation, source reference/link, observation timestamp, and confidence where available. Re-running the service against unchanged inputs and profile version produces the same result. Scores are computed from current stored records at request time; this feature does not persist assessment snapshots.

All displayed scores are heuristic outputs. Synthetic fixtures and unverified source records are not presented as validated market evidence. An empty result, inaccessible source, missing input, and stale input remain distinct from a numeric zero.
