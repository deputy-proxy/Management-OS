# Product cost allocation: current-state audit v1

**Status:** Engineering audit; not an approved accounting policy  
**Date:** 2026-10-27  
**Related roadmap:** [Issue #353](https://github.com/deputy-proxy/Management-OS/issues/353) and [Issue #354](https://github.com/deputy-proxy/Management-OS/issues/354)

## Purpose

Record what the existing product-cost allocation service does, identify technical risks that can be tested without inventing accounting policy, and list decisions that need an accountable product/finance owner before the allocation model is expanded.

This document is not a statement that a cost is direct, eligible, deductible, recognized in a particular period, or attributable to a particular product under accounting rules.

## Observed implementation

The current `App\\Services\\ProductCostService::recalculateForResource()` method:

1. Deletes all `ProductCost` rows for the resource's `product_resource_id`.
2. Loads the resource's allocations.
3. Creates one `ProductCost` row per allocation, copying product, resource, month, percentage, and resource amount.
4. Calculates each allocated amount as `intval(round(resource amount × allocation percentage / 100))`.
5. Sums allocation percentages and updates the resource's `allocated_percentage` while suppressing model events.

This is a mechanical percentage allocation. The service does not itself establish the source cost's business meaning, currency, eligibility, allocation rationale, approval state, or link to a vendor invoice, labour/activity record, or bank transaction.

## Risks to verify before extending the service

- **Atomicity:** deletion, row recreation, and the resource percentage update should be treated as one operation. A failure part-way through may leave incomplete allocation rows unless the caller guarantees a database transaction.
- **Percentage validation:** this method does not validate that percentages are non-negative, within an allowed range, or sum to an approved target. Validation may exist elsewhere; verify it before adding duplicate controls.
- **Rounding residuals:** independently rounding each allocation can make the sum of allocated amounts differ from the source resource amount, even when percentages total 100%.
- **Integer precision and units:** the output is an integer. The service does not document whether amounts are minor units, whole currency units, or another scale; do not infer or convert units here.
- **Currency compatibility:** no currency field or cross-currency check is used in this method. Do not aggregate resources of different currencies without an approved conversion policy.
- **Traceability:** the generated rows retain the resource ID, product ID, month, percentage, and copied amount, but the method shown does not store a rule version, allocation reason, approval reference, or immutable calculation snapshot.
- **Correction history:** existing rows are deleted and recreated. This method alone does not preserve prior calculation versions for audit.
- **Duplicate attribution:** this method allocates a resource across its configured allocation rows, but it does not establish whether the source resource is already represented in another cost source or whether two resources refer to the same underlying expense.

These are observations about this method, not claims that the wider application has no corresponding protections.

## Decisions required from the business/finance owner

Before defining production attribution rules, record decisions and examples for:

1. Which source types are eligible for direct-cost attribution (for example, purchases, vendor invoices, labour/activity), and which are excluded.
2. The authoritative source record and uniqueness key used to prevent double-counting.
3. Whether allocation percentages must total exactly 100%, may be partial, or may exceed 100% under any approved case.
4. How rounding residuals are assigned, and the monetary unit and currency source for every amount.
5. Which period determines attribution when a source date, invoice date, service period, and payment date differ.
6. How shared costs and missing product/resource links are handled.
7. Whether recalculation overwrites current state, retains immutable versions, or records adjustment events.
8. Who can approve changes, how exceptions are reviewed, and what audit evidence is retained.

Each approved rule should include a named owner, effective date/version, representative source-linked examples, expected outputs, and explicit exclusions. Accounting or tax treatment requires qualified review; this audit does not supply that approval.

## Safe engineering follow-up

- Add tests for transactional rollback, invalid percentage inputs at the established validation boundary, rounding residual behavior, repeatable recalculation, and duplicate-source protection once the applicable contracts are defined.
- Inspect callers, model observers, migrations, validation rules, and existing tests before changing service behavior.
- Keep existing dashboards compatible unless a separately reviewed migration defines the intended change.
- Do not silently backfill historical allocations or reinterpret legacy amount units.

## Acceptance status

This audit makes the current implementation and open decisions reviewable. It does **not** satisfy the requirement for approved attribution rules or specialist sign-off, and it does not authorize new accounting calculations.
