# Sales-source attribution policy

**Policy version:** `v1`  
**Scope:** Customer-lead acquisition source capture and operational reporting.  
**Effective date:** 2026-10-27

## Purpose

Capture a consistent source category while retaining the original free-text source detail. This policy describes lead acquisition metadata; it does not prove marketing incrementality, campaign spend, customer revenue, or causal attribution.

## Controlled source categories

| Value | Meaning | Guidance |
| --- | --- | --- |
| `organic_search` | Unpaid search discovery | Use only when the lead or captured evidence identifies organic search. |
| `paid_search` | Paid search advertising | Add campaign name/ID when known; do not infer platform or spend. |
| `social` | Social media | Keep paid versus organic detail in source details/campaign where known. |
| `referral` | Individual or organization referral | Record referral partner/name when known and permitted. |
| `partner` | Partner-originated lead | Record partner detail when known. |
| `event` | Event, conference, webinar, or similar | Record event/campaign when known. |
| `outbound` | Direct outbound sales activity | Preserve outreach detail in source details. |
| `direct` | Direct contact with no attributable intermediary | Use only when supported by intake information. |
| `website` | Website or inbound form where the channel is not more specific | Do not upgrade to organic/paid search without evidence. |
| `existing_customer` | Introduction or expansion from an existing customer | Does not by itself establish referral compensation. |
| `other` | Known source that does not fit the categories | Explain it in source details. |
| `unknown` | Unknown, unavailable, or legacy source not yet mapped | This is the migration default; it is not a claim that no source existed. |

## Capture and history rules

- `source_category` is the normalized reporting dimension. `source` remains the original descriptive detail and must not be silently rewritten.
- Existing leads receive `unknown` for the normalized category. No category is inferred from legacy free text by migration.
- Campaign and referral fields are optional. Missing values remain null; no campaign ID, referral, advertising platform, or spend is invented.
- `attribution_policy_version` records the policy version used when captured. `source_captured_at` records the time when the source was captured if known, not an inferred lead creation time.
- Source metadata changes are activity-logged and appended to `customer_lead_attribution_events` as immutable snapshots. The first-touch event records the initial captured state; each subsequent attribution edit appends a last-touch candidate. Existing lead status history remains separate from attribution metadata.
- Conversion appends one idempotent conversion event containing separate lead-level `first_touch` and `last_touch` snapshots plus the linked customer and conversion timestamp. These snapshots describe this lead only; they do not assign customer-level or multi-touch marketing credit. Leads that predate event capture have no fabricated first-touch event, and their conversion snapshot keeps first touch unavailable.
- Multiple leads may refer to one customer. Do not deduplicate or merge them automatically, and do not treat a lead source as a customer's unique first-touch source without a separately defined customer-level attribution rule.
- A lead's source category alone is not enough to calculate CAC, CLV, retention, or campaign ROI. Those reports require separately approved cost, conversion, identity, revenue, and observation-window contracts.

## Reporting and quality

Reports must group on the controlled category and show `unknown` and `other` separately. Do not silently exclude unknown values. Campaign-level metrics must disclose missing campaign identifiers and must not assign cost or revenue to a campaign without an approved matching rule.

## Validation

The application rejects categories outside the controlled vocabulary. Legacy source detail is preserved. Tests cover migration-compatible unknown values, valid category changes, invalid category rejection, immutable attribution history, conversion snapshots, duplicate conversion attempts, and exact capture-time boundaries.

## Limitations

This policy is version `v1` and is an operational taxonomy, not a claim of complete advertising attribution. It does not establish customer-level first-touch/last-touch/multi-touch credit, cross-device identity resolution, campaign cost allocation, or Google Ads synchronization. Lead-level first/last snapshots are audit evidence only. Customer-level credit and production taxonomy approval require separate contracts and tests.
