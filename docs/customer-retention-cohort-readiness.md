# Customer retention and cohort reporting readiness audit

## Scope

This audit assesses whether the current customer, lead, contract, and interaction records support reproducible retention and cohort metrics. It does not introduce a churn definition or report a retention rate without an approved business rule.

## Source-backed fields available today

- `CustomerLead.converted_at` records the conversion timestamp for a lead that was converted through the existing workflow.
- `CustomerLead.converted_customer_id` links a lead to the resulting customer. Multiple lead records may refer to a customer, so a cohort must count customers rather than blindly counting converted leads.
- `Customer.created_at` is a record-creation timestamp, not necessarily the first paid-service or customer-activation date.
- `CustomerContract.start_date` and `end_date` describe a contract interval when populated. An end date alone does not prove cancellation, non-renewal, or churn; contracts can be missing, overlapping, or replaced.
- `CustomerInteraction.timestamp` records observed interactions. No interaction during a period is not proof the customer became inactive or churned.
- Soft-deleted rows can be excluded from ordinary model queries, but deletion is not a documented churn event and should not be used as a substitute for churn.

## Readiness gaps

1. There is no approved definition of customer activation or the event that places a customer into a cohort.
2. There is no explicit customer churn/inactive status or effective-dated churn event in the reviewed model. Contract end dates are insufficient to infer one.
3. Retention interval, observation window, timezone, grace period, treatment of pauses, renewals, overlapping contracts, and reactivation are not approved.
4. Lead conversion timestamps are useful for conversion cohorts, but a customer may have multiple source leads; cohort assignment needs a policy such as first verified conversion, not an unreviewed query shortcut.
5. Legacy records may lack conversion timestamps, contracts, or interactions. These must be reported as unknown/excluded with counts, not silently treated as churn or retained.
6. Attribution policy remains unresolved under issue #377. Cohort reporting that segments retention by acquisition source depends on that decision.

## Safe implementation boundary

Until the definitions above are approved, the dashboard may expose source-record counts and contract end-date review lists, but must not label those counts as retention, churn, renewal rate, or customer lifetime. A future report should show its cohort rule, denominator, observation cutoff, excluded/unknown counts, and links or IDs that allow sampled results to be reconciled to source records.

## Decisions required before retention rates

- Business owner approves cohort event and customer identity/deduplication rule.
- Business owner approves retained/active/churned definition, period, grace window, and reactivation treatment.
- Data owner approves timezone, observation cutoff, treatment of missing/soft-deleted records, and how unknown values are displayed.
- Sales attribution owner resolves issue #377 before source-segmented retention is added.

## Validation plan once definitions are approved

Create synthetic fixtures for converted customers with one or multiple leads, customers with and without contracts, contracts ending inside and outside the observation window, missing timestamps, interaction-only records, reactivation, and soft deletion. Independently calculate expected cohort denominators and retained counts, then test date boundaries and reconciliation against source IDs. No historical event should be backfilled unless supported by source evidence.
