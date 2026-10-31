# Public market source and provider evaluation v1

**Status:** Source evaluation based on public documentation reviewed on 2026-10-28. Production access, account eligibility, contractual permissions, and cost quotes have not been verified for this organization.  
**Roadmap:** [Issue #360](https://github.com/deputy-proxy/Management-OS/issues/360)

## Decision summary

Use a tiered approach rather than building an indiscriminate scraper:

1. **Official structured statistical sources** such as Eurostat for macroeconomic and regional context where the dataset definition matches the business question.
2. **Google Ads API** for first-party advertising performance, but only after the organization confirms Google Cloud project access, account authorization, selected metrics, reporting-only permissible use, and the applicable quotas.
3. **Google Trends web export** for limited manual research and hypothesis generation. Treat its normalized index as search interest, not absolute search volume or proof of product demand. The official Trends API was documented as a limited alpha program in the source reviewed; do not assume general production access.
4. **Competitor websites and published offers** for focused, human-reviewed observations. Prefer manual capture or an authorized provider/API with documented terms. Do not introduce automated scraping until source-specific permission and terms have been reviewed.
5. **Romanian official statistics** may be useful, but the precise dataset/API coverage, terms, machine access, and refresh schedule must be verified against the current official INSSE service before selecting it as an automated feed.

No provider should be marked production-approved based only on public documentation. Actual account access, contract terms, source coverage for the selected Romanian segments, and any commercial fees remain unverified.

## Candidate comparison

| Candidate | Suitable use | Access and cadence evidence | Limitations and terms gate | Recommendation |
| --- | --- | --- | --- | --- |
| Eurostat REST/SDMX API | EU and country-level economic/demographic indicators that have a suitable dataset and geography | Official documentation describes free programmatic access to statistical metadata/data; the database is refreshed twice daily when newer data or structural changes are available. [API introduction](https://ec.europa.eu/eurostat/web/user-guides/data-browser/api-data-access/api-introduction), [getting started](https://ec.europa.eu/eurostat/web/user-guides/data-browser/api-data-access/api-getting-started) | Coverage is dataset-specific; the latest database is not a versioned archive of all prior releases. Validate each dataset's definition, unit, geography, revision behavior, and reuse conditions before ingestion. | **Preferred first external structured source** for matching macro indicators. Keep source dataset code, extraction time, dimensions, unit, and any revision metadata. |
| Google Ads API | First-party campaign delivery and performance metrics for accounts the organization is authorized to access | Google documents OAuth credentials and API access management. Its current access-level documentation describes project-level access, including Explorer access with up to 2,880 production operations/day and Basic access with up to 15,000 operations/day; system and service-specific rate limits also apply. [Access levels](https://developers.google.com/google-ads/api/docs/api-policy/access-levels), [quotas](https://developers.google.com/google-ads/api/docs/best-practices/quotas), [authorization](https://developers.google.com/google-ads/api/rest/auth) | Requires approved project access and authorized account credentials; available features depend on access level/permissible use. Quotas and provider attribution definitions constrain reporting. The documented access process changed in September 2026, so use the current Cloud Console process, not old developer-token setup instructions. | **Preferred for first-party ads**, conditional on verifying actual account/project access and approving a narrow read-only metric contract. |
| Google Trends UI/export | Manual discovery and contextual signals about relative search interest | Official help documents CSV export and citation/attribution. [Export and cite](https://support.google.com/trends/answer/4365538), [FAQ](https://support.google.com/trends/answer/4365533) | Values are normalized relative interest, not absolute query counts; low-volume values can appear as zero, and noise/irregular activity can affect observations. Official API documentation reviewed describes alpha access limited to selected testers, not universal availability. [API alpha](https://developers.google.com/search/apis/trends) | **Use manually at first** with saved query parameters, export date, geography, time range, and Google attribution. Do not build a production dependency on alpha access. |
| Competitor public websites and offer pages | Current published price, promotion, availability, and terms for explicitly selected competitors | Access varies by site; no common public API, refresh target, price, or permission was established by this evaluation. Capture the canonical source URL, observation time, relevant offer terms, and operator/source method. | Public visibility alone does not establish permission for automated extraction, bulk retention, redistribution, or reuse of images/text. Robots rules, site terms, copyright/database rights, and jurisdiction-specific issues require source-by-source review. Price comparisons are invalid without product/service equivalence, currency, price basis, geography, validity, and promotion terms. | **Manual, focused observation first**. Consider an authorized provider/API only after documented coverage, price, rights, provenance, retention, and exit terms are reviewed. No scraping implementation approved. |
| Romanian National Institute of Statistics (INSSE/TEMPO) | Romanian official statistics where relevant datasets and dimensions exist | Potentially relevant as an official national source, but this review did not verify a specific machine-readable endpoint, dataset coverage, current terms, or API refresh guarantee. | Do not assume API availability, automated reuse permission, or that a national statistic is granular enough for competitor/product decisions. Verify official endpoint documentation, dataset codes, units, revisions, licensing/attribution, and access method before selection. | **Candidate requiring further verification**, not yet an approved automated source. |
| Commercial market-data provider | Structured competitor prices, product matching, or specialized industry indicators | Access, coverage, SLA, and pricing are vendor- and contract-specific and were not verified in this public-documentation review. | Requires a quote and contract review covering territories, product coverage, update frequency, provenance, audit rights, retention, redistribution, rate limits, service credits, and termination/export rights. | **Defer until manual baseline and data requirements justify cost**. Do not invent price estimates or imply a vendor was contacted. |

## Recommended initial source portfolio

### Phase 1: low-commitment validation

- Use Eurostat only for a short list of owner-approved indicators that directly inform a defined decision. Store dataset identifiers and exact dimensions so a value can be reproduced.
- Use Google Trends as a manual research aid. Preserve the query/topic, geography, time window, export timestamp, and attribution. Label the value as normalized search interest.
- Record competitor offers through a controlled manual workflow for a small owner-selected competitor set. Preserve historical observations, source URL, capture time, currency, price basis, validity dates, and promotion/availability terms.
- Audit the existing Google Ads daily metric model and request/verify account/project access before scheduling imports. Start with read-only reporting and the minimum required metrics.
- Do not purchase a provider contract or implement scraping until source coverage, terms, ownership, and success criteria are approved.

### Phase 2: source verification checklist

For each proposed feed, require a completed record of:

1. Official documentation and exact endpoint/API/product version.
2. Dataset and metric definitions, units, geography, granularity, and known coverage gaps.
3. Access method, authentication, account ownership, approval process, quota, and rate limits.
4. Terms, permitted purposes, attribution, retention, display/redistribution rights, and restrictions.
5. Expected update cadence, observed latency, revisions/backfills, and outage communication.
6. Cost evidence: free official access, published pricing, or a dated vendor quote. Mark unknown if not verified.
7. Source identifiers, capture timestamps, idempotency key, and reproducible query/parameters.
8. Failure behavior: unavailable credentials, partial response, schema changes, stale data, and safe retry.
9. Data owner, operational owner, permission model, retention/deletion process, and business decision served.
10. Exit path if access is revoked, terms change, or the source stops meeting quality requirements.

## Data and provenance requirements

- Distinguish provider observations, manual observations, internal calculations, and estimates in stored data and UI.
- Keep the provider's metric meaning and unit intact. Do not treat Trends index values as absolute search counts, ad conversions as recognized revenue, or macro indicators as direct evidence of demand for a specific product.
- Preserve source URL/dataset/query, provider/account identifier where permitted, observed-at time, imported-at time, coverage period, method/version, and correction/revision evidence.
- Store last successful refresh and partial/error state separately from the last valid observation. A failed refresh must not erase valid historical data or create a false zero.
- Normalize currency or units only under an approved, documented rule. Do not compare incomparable competitor prices.
- Apply existing permissions to both aggregates and drill-down. Avoid unnecessary personal data and never log credentials or tokens.

## Implementation gates and unresolved questions

- Which Romanian business segments, geographies, competitor set, and market decisions are in scope first?
- Which Eurostat/INSSE datasets actually answer the selected questions, and who approves their interpretation?
- Is Google Ads project/account access already approved, and which account IDs and read-only metrics are authorized?
- Is Google Trends manual export sufficient, or is the organization eligible for its limited API alpha?
- Which competitor sites permit the intended collection and retention method? Who is responsible for observations and validation?
- What minimum coverage and freshness are required to justify a paid provider?
- Who owns each source, approves definitions, monitors failures, and handles deletion/retention requests?

## Acceptance status

This is a public-documentation comparison, not a legal opinion, contractual approval, vendor quote, or live access test. Eurostat is the strongest initial candidate for suitable official macro indicators; Google Ads is the candidate for authorized first-party advertising data; Google Trends and competitor sites should initially be used with manual, provenance-preserving workflows. INSSE/TEMPO automation and paid provider selection remain unverified. No integration or scraping is approved by this document.
