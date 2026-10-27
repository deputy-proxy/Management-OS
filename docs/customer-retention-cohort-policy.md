# Customer retention cohort policy

**Policy version:** `v1`  
**Effective date:** 2026-10-27  
**Timezone:** `Europe/Bucharest`  
**Scope:** Operational retention cohorts for customers with verified conversion events and explicitly recorded lifecycle changes.

## Cohort assignment

- A customer enters the acquisition cohort at the earliest `converted_at` timestamp among non-deleted leads whose status is `converted` and whose `converted_customer_id` points to that customer.
- Count customers, not leads. If multiple leads point to one customer, the earliest valid conversion timestamp wins; lead ID is the deterministic tie-breaker.
- Group by calendar month in Europe/Bucharest. `Customer.created_at`, contract start/end dates, interactions, and soft deletion are not activation/churn evidence.
- Retention tracking starts at `CUSTOMER_RETENTION_TRACKING_START_AT` (default `2026-10-27 00:00:00`). Do not publish retention rates for earlier cohorts because complete lifecycle event coverage is not established.
- Customers without a verified conversion timestamp, or with conversion before tracking start, are excluded from retention-rate denominators and reported as unknown/excluded counts.

## Lifecycle events

- `churned` is an explicit, evidence-backed event that a customer relationship ended. Contract end date, no interaction, invoice status, and soft deletion alone do not qualify.
- `reactivated` is an explicit, evidence-backed return to active service after a churn event.
- Events require an effective timestamp, reason, and controlled evidence reference. Do not store sensitive documents or personal data in the evidence-reference field.
- Events are append-only. Correct a mistake with a separately recorded, reviewable event or future correction mechanism; never rewrite or delete the original record.
- Events must alternate between active-to-churned and churned-to-active. Duplicate churn or reactivation transitions are rejected.

## Monthly retention calculation contract

For a cohort month and each elapsed monthly interval:

1. Denominator: unique customers assigned to that cohort with verified conversion in the tracking window.
2. Retained at interval end: customers whose latest explicit lifecycle event at or before the interval cutoff does not indicate `churned`. The acquisition month is active at the conversion event by definition.
3. Churned customers become retained again only after an explicit `reactivated` event effective by the cutoff.
4. Report cohort month, interval cutoff, denominator, retained count, churned count, excluded/unknown count, and source reconciliation IDs. If lifecycle event coverage or cutoff is incomplete, mark the result incomplete rather than presenting a percentage as authoritative.
5. Retention percentage is `retained_count / denominator * 100`, rounded to two decimal places for display only. Never divide by zero; show unavailable with denominator zero.

## Governance and privacy

- Only users with customer-update permission may record lifecycle events. Event history is visible through the customer record's existing access controls.
- Record the actor and timestamp separately from the effective date.
- Use synthetic fixtures for testing; do not store customer-sensitive evidence in repository tests or logs.
- This policy does not define revenue, CAC, CLV, campaign credit, or accounting retention. Those metrics require their own approved financial contracts.

## Validation coverage

Tests cover missing cohort anchors, evidence requirements, transition ordering, and immutability. Dashboard publication must additionally test monthly timezone boundaries, duplicate leads per customer, missing conversion timestamps, reactivation, zero denominators, permission scopes, and reconciliation to customer/lead/event IDs.
