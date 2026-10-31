# Market intelligence objectives and data requirements v1

**Status:** Engineering requirements proposal; business-owner approval is pending  
**Roadmap:** [Issue #359](https://github.com/deputy-proxy/Management-OS/issues/359)

## Objectives and decisions supported

Market intelligence should support explicit business decisions, not accumulate charts merely because data is available. Proposed use cases:

1. Compare competitor offers with our comparable products/services and identify material price, availability, and terms differences.
2. Track market trends over a defined geography, segment, and observation period, with source and methodology visible.
3. Monitor advertising delivery and efficiency for authorized Google Ads accounts, with campaign mapping and attribution limitations explicit.
4. Distinguish observed facts, internally calculated metrics, estimates, and third-party/provider-reported values.
5. Flag stale, incomplete, unmapped, or anomalous inputs so missing information is not presented as zero or as a market movement.

These use cases require owner confirmation, priority, named decision owner, and measurable success criteria before integrations or additional schema are implemented.

## Existing structures inspected

- `MarketTrend` supports a trend name/category/metric key, numeric value and unit, market/segment, period boundaries, source name/URL, observation timestamp, prior value/change percentage, confidence, methodology, metadata, and status. The model validates period ordering and selected value constraints. This is a usable trend record surface, not proof that a reliable provider feed or refresh process exists.
- `Competitor` stores competitor identity and site/social links plus locations. Its current shape does not itself provide a complete, timestamped history of every competitor profile change.
- `CompetitorOffer` stores offer information including competitor link, type/description, price, currency, price basis, validity period, source URL, observed timestamp, and status.
- `MarketAnalysisDashboardData` summarizes competitor, offer, historical-offer-observation, and Google Ads data subject to supplied permissions. Existing dashboard aggregation and access checks should be extended rather than replaced.
- The repository has dedicated historical offer observation and Google Ads daily metric models, but this requirements proposal does not certify live provider access, data completeness, account authorization, or production refresh health.

## Proposed data contract

| Data domain | Required fields and provenance | Freshness target proposal | Primary owner proposal | Quality and missing-data behavior |
| --- | --- | --- | --- | --- |
| Competitor profile | Stable internal ID; name; canonical website; relevant geography/locations; source URL; captured/updated time; who or what supplied the change | Review at least quarterly; refresh sooner when an offer observation finds a material change | Market intelligence/business owner | Preserve known profile and flag unverified fields; do not invent a location or infer a competitor identity from a similar name |
| Competitor offer | Competitor and offer IDs; source URL; observed-at timestamp; validity window if published; price, currency, price basis, promotion terms, availability, geography/product match, capture method | Initial proposal: review high-priority offers weekly; lower-priority offers monthly; provider/API cadence only after terms and feasibility review | Market intelligence owner; named capture operator/provider | Distinguish not observed, unavailable, expired, and unknown; retain historical observations instead of overwriting them |
| Market trend | Metric key and definition; value/unit; geography/segment; period start/end; source/provider; observed-at; methodology; confidence and status | Set per metric and provider; show last observation and stale state; default proposal no more than one expected refresh interval late | Business owner per metric, with data steward | Validate units, period, duplicate observations, source provenance, and methodology; missing/stale values are not zero |
| Advertising metrics | Authorized customer/account IDs; provider campaign/ad-group IDs; mapped internal campaign/product where approved; metric name/unit; date grain/timezone; currency where relevant; source and sync timestamp | Daily import proposal, subject to API quotas, account permissions, and source availability | Marketing owner and Google Ads integration owner | Record partial failures, unmapped campaigns, unavailable credentials, late revisions, currency mismatch, and provider-reported attribution limitations |
| Derived comparison | Exact source observation IDs; matching rule/version; normalized units/currency only where a reviewed rule exists; calculation timestamp; exclusions | Recompute after a source observation changes, with a visible last-calculated time | Owner of the decision/report | Show unmatched/incomparable rows as exceptions; never silently coerce units, currencies, or missing values |

Freshness targets above are proposals for owner review, not current service-level guarantees. Each metric needs a separately approved definition, source, expected cadence, maximum age, owner, confidence/quality criteria, and intended decision.

## Metric definitions and comparability

Before a metric is exposed in a dashboard, document:

- **Definition:** business meaning, unit, grain, formula where derived, and inclusion/exclusion rules.
- **Source:** first-party, third-party/provider, manual observation, or estimate; include source reference and captured/observed timestamp.
- **Time semantics:** event/observation date, period boundaries, timezone, expected latency, and correction/revision behavior.
- **Dimensions:** geography, segment, product/service mapping, competitor, campaign, and currency only where the source supports them.
- **Quality:** completeness, confidence or verification status, expected refresh interval, stale threshold, duplicate handling, and exception codes.
- **Ownership:** business decision owner, data steward, integration operator, and approver for definition changes.
- **Use limitation:** intended decision and known bias, coverage gaps, attribution limitations, and source terms.

Do not compare competitor prices unless product/service equivalence, geography, currency, price basis, tax/fee treatment where known, promotion conditions, and observation dates are sufficiently comparable. If comparability is unknown, show the records side by side with a warning rather than manufacturing a normalized score.

Advertising metrics must retain provider definitions and attribution windows. Provider-reported conversions or value are not automatically equivalent to internal revenue, profit, or customer acquisition cost.

## Source access, permissions, and retention

- Record source terms, permitted collection method, API access requirements, and any restrictions on storage, display, redistribution, and historical retention before ingestion.
- Do not add scraping or credential collection without an approved source review. Use least-privilege provider scopes and keep secrets out of logs and stored observations.
- Preserve source identity and observation timestamps. Keep raw provider identifiers needed for idempotency and audit, but avoid unnecessary personal data.
- Respect existing dashboard and model permissions. A summary must not expose underlying competitor, campaign, or account detail to a user who cannot view it.
- Define retention and deletion behavior by source contract and applicable policy; do not assume all third-party data may be stored indefinitely.
- Separate source data from derived metrics and estimates. Label each visibly in API/service outputs and UI.

## Failure and refresh behavior

1. Each source integration should report last successful refresh, last attempted refresh, coverage window, partial failure, and next eligible retry where available.
2. Retries must be bounded and idempotent. Repeated imports must not create duplicate observations; provider corrections must be handled under documented update/version rules.
3. Missing credentials, revoked scopes, rate limits, incomplete pages, unavailable providers, and invalid source records must produce observable error/stale states, not successful empty imports.
4. A failed refresh must not erase the last valid observation. Preserve it with an explicit stale indicator until a new valid observation arrives.
5. Unknown currency/unit, unmapped campaign/product, ambiguous competitor match, and unsupported period must be surfaced as exceptions.
6. Monitoring should measure freshness compliance, source coverage, import success/partial-failure rate, duplicate rate, mapping coverage, and unresolved exception age.

## Proposed success measures

Owners should approve targets for the following measures before delivery:

- Share of priority competitor offers with a source URL, observation time, currency/price basis where applicable, and current freshness status.
- Share of trend metrics with a named owner, definition, source, methodology, and freshness threshold.
- Google Ads import completeness for authorized accounts and selected date windows, plus visible failure detection and recovery.
- Percentage of campaign records mapped to internal campaigns/products, without forcing ambiguous matches.
- Time from source failure to detection and recovery; count and age of unresolved data-quality exceptions.
- Evidence that a defined business decision (such as offer repricing or campaign review) used the data, without treating dashboard usage alone as business impact.

## Staged delivery and approval gates

### Stage A: approve requirements
Confirm priority use cases, metric owners, required geographies/segments, target freshness, source access/terms, retention, permission rules, and success thresholds. Resolve whether manually captured observations are in scope alongside provider feeds.

### Stage B: source and schema readiness
Complete the source/provider evaluation in issue #360. Map approved requirements to existing `MarketTrend`, `Competitor`, `CompetitorOffer`, historical observation, and Google Ads metric structures. Propose only necessary schema changes with migration and compatibility tests.

### Stage C: integrations and dashboard
Implement ingestion only after access, ownership, terms, idempotency, revision handling, and refresh/error contracts are approved. Extend the existing dashboard with source, freshness, quality, and comparability indicators; preserve existing permission checks.

### Stage D: validation
Use synthetic fixtures and provider-approved test accounts to verify pagination, repeated imports, partial failures, stale data, source corrections, timezone/date boundaries, mapping gaps, currency/unit incompatibility, and authorization. Reconcile displayed metrics against source records and document known limitations.

## Open decisions

- Which market/product segments and geographies are first priority?
- Who owns each metric and approves definition or source changes?
- Which competitors and offer types are in scope, and what qualifies as a comparable offer?
- Which data may be captured manually, and which sources/providers are approved?
- What freshness targets and stale thresholds apply by metric?
- What retention is permitted by each source contract?
- Which Google Ads accounts, metrics, dimensions, and attribution windows are approved?
- Which internal campaign/product mappings are authoritative?
- What success thresholds will be used to evaluate business value?

## Acceptance status

This document proposes a requirements contract grounded in the existing model/service surface. It is not an approved business specification, provider-access approval, legal/terms review, or proof of live data freshness. No integration or calculation behavior is changed here; implementation remains gated on explicit owner decisions and source review.
