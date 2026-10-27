# Market intelligence dashboard and opportunity-signal policy

**Version:** 1.0  
**Effective date:** 2026-10-27  
**Scope:** Stored market-trend observations, competitor-offer observations, and imported Google Ads campaign-day metrics shown in Management-OS.

## Purpose and decision boundary

The dashboard combines three evidence classes without pretending they are interchangeable:

1. **Market trend observations** describe a source-reported metric over a declared market, segment, and period.
2. **Competitor-offer observations** preserve the offer terms, price basis, currency, validity, source, and observation/recording timestamps available at capture time.
3. **Google Ads campaign metrics** are provider-reported performance for a specific advertising account, campaign, date, and account currency.

The dashboard is decision support, not an automatic pricing, product-launch, budget-allocation, or campaign-pausing system. A trend, competitor offer, or ad conversion is not proof of demand, a unique customer, revenue, profit, or causal impact.

## Market trend contract

- Display active `MarketTrend` records by observation time, newest first, with a bounded recent list.
- Preserve metric key, value, unit, market, segment, period, source name/URL, observed time, reported change, and confidence as supplied.
- A reported percentage change is displayed as source-recorded metadata. The dashboard does not recalculate it unless the source contract explicitly defines comparable values and formula.
- Do not merge different metric keys, units, markets, segments, or periods into one aggregate.
- `confidence` is shown as supplied on the underlying record and is not converted into an approval status.
- Archived records are excluded from the active view, not deleted.
- Missing observations are unavailable data, not zero demand. Missing sources, dates, segments, and confidence remain visible as unknown/unspecified.

## Google Ads metric contract

- Only stored imports are read during dashboard rendering. Rendering never initiates a provider API call.
- The default reporting window is the current application-local date and the preceding 29 calendar dates, inclusive.
- Impressions and clicks are integer sums; conversions are provider-reported decimal sums; cost is stored in micros and converted for display using the provider's 1,000,000-micros-per-currency-unit representation.
- Cost and campaign cost-per-reported-conversion remain grouped by account currency. No exchange-rate conversion or cross-currency total is produced.
- A zero denominator makes the corresponding ratio unavailable. No divide-by-zero fallback or invented zero ratio is allowed.
- Provider-reported conversions are not treated as unique customers or paid/recognized revenue. These values must not be labelled CAC, customer acquisition, ROI, or profit.
- Imported metric freshness is reported separately from campaign performance. An empty window means no stored rows were available, not that spend, impressions, or conversions were zero.
- Access to imported campaign metrics requires `paid_campaign.index`; competitor and market-trend data retain their own resource permissions.

## Opportunity-signal review

An opportunity is a human-reviewed hypothesis supported by one or more observations, not an automatic score. Reviewers should record:

- the product/service and target segment under consideration;
- the specific trend or demand observation and its source/period;
- relevant competitor-offer observations with currency, price basis, validity and provenance;
- relevant campaign metrics, account currency and reporting window, when applicable;
- contradictory evidence, missing data and freshness limitations;
- the proposed action, owner, review date and decision outcome.

Do not rank or auto-recommend products based only on positive trend change, a missing competitor offer, or ad conversions. The application currently has no approved mapping from generic market metrics to the company's product/service catalogue and no approved scoring weights or decision thresholds. Until that mapping is configured and tested, the dashboard displays the underlying signals and provenance rather than inventing a market-opportunity score.

## Validation and quality checks

- Reject or flag invalid trend periods, out-of-range confidence values, unknown metric definitions, absent provenance and stale observations according to the source contract.
- Keep competitor offer prices separated by currency and price basis; never average incomparable offers.
- Validate imported campaign-day identity, account/campaign mapping, currency consistency, non-negative counts/cost, provider version and import timestamp before upsert.
- Repeated imports for the same account/campaign/date must be idempotent; preserve import/run evidence outside the aggregate dashboard.
- Test permission boundaries, empty windows, stale/future observations, zero denominators, multiple currencies, and archived records.

## Limits and follow-up

This policy does not establish live Google Ads connectivity, external market-source completeness, product-market fit, causal attribution, CAC/CLV, or revenue attribution. Those require separate source contracts and validation. The dashboard should not imply live data or verified provider coverage when only stored observations are available.
