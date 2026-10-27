# Financial budget entry and versioning: current-state audit v1

**Status:** Engineering scope audit; financial budget contract not yet implemented  
**Date:** 2026-10-27  
**Roadmap:** [Issue #356](https://github.com/deputy-proxy/Management-OS/issues/356)

## Existing capability

Repository search and the current financial data contract show no dedicated versioned financial-budget entity or workflow for budgets by period, category, product/service, and currency.

There are unrelated budget-like fields in existing models, including `Project.budget` and `PaidCampaign.budget`. The project and campaign forms expose a required numeric/integer field, and their records have activity logging. These fields do not establish a financial planning contract: the inspected schema does not define a common currency, budget period grain, category mapping, version identity, approval state, effective date, immutable revision history, or relationship to approved actuals. They must not be treated as a substitute for a financial budget ledger.

The current finance overview is an operational summary of transactions, classifications, canonical cash movements, and invoice status counts. It does not consume a versioned budget, and it does not calculate budget variances. The financial data contract also leaves legacy monetary units and source mappings unresolved and requires currency separation without automatic FX conversion.

## Gaps and invariants

1. **Budget scope and grain are undefined.** The domain must decide whether a budget is owned by the company, department/category, product/service, project, or a combination, and whether entries are monthly, quarterly, annual, or another approved period grain.
2. **Amount semantics need a contract.** Each entry requires explicit amount units, currency, sign/adjustment semantics, precision, rounding, and period boundaries. Do not infer currency or scale existing integer fields.
3. **Versions must be identifiable and auditable.** A budget revision needs a stable version identity, creation actor/time, effective period, revision reason, and a rule for whether published versions are immutable or corrected through a new version. Activity logs alone do not establish a domain-level version-selection contract.
4. **Approval transitions are unspecified.** Define draft, submitted, approved, rejected, superseded, and archived states only after an owner approves the vocabulary and transition/permission matrix. Approval identity, outcome, timestamp, and reason need durable audit evidence.
5. **Comparability must be explicit.** Budget dimensions and currency must match the selected actuals contract. Do not silently combine currencies, category mappings, or periods that do not align.
6. **Missing values are not zero.** Unmapped categories, absent currency, overlapping versions, missing periods, and unsupported actual classifications should remain visible exceptions rather than silently filled values.
7. **Historical reports need deterministic version selection.** Define which approved version is used for each report period and how later revisions affect previously issued comparisons. Preserve the selected version and formula metadata with report output.
8. **Permissions and exports need dedicated controls.** Define who can draft, edit, submit, approve, supersede, view, and export budgets; enforce authorization server-side and audit sensitive actions.
9. **Reconciliation is part of acceptance.** Test fixtures must independently reconcile entered entries, approved version totals, and period/category/currency rollups. Tests should cover duplicate submissions, concurrent edits, invalid periods, conflicting versions, and unauthorized transitions.

## Safe staged delivery

### Stage A: approve budget semantics

- Define budget owner and dimensions, period grain and timezone, category/product/service mapping, amount units, currency, precision, adjustment semantics, and handling of missing or incompatible actuals.
- Define version lifecycle, approval roles, effective dating, revision reasons, publication rules, and historical-report behavior.
- Decide whether existing project/campaign budget fields remain separate operational metadata or have an explicitly reviewed migration path. Do not automatically import them into a new financial budget.
- Record examples with expected outcomes and accountable approval evidence.

### Stage B: implement versioned entry

- Add a dedicated financial budget model only after the contract is approved; keep drafts distinct from approved versions.
- Enforce period/dimension/currency compatibility and approval transitions at the server boundary.
- Preserve approved versions and represent changes through auditable revisions rather than silent overwrites.
- Store enough provenance to reproduce a selected version and its totals.
- Add model, service, feature, authorization, concurrency, and migration tests using synthetic fixtures.

### Stage C: operational reporting readiness

- Provide version selection, approval status, period and currency labels, revision history, and exception visibility.
- Keep budget entry independent from budget-versus-actual reporting until the actuals mapping is approved.
- Independently reconcile rollups to approved entry rows and verify that no currency or period is silently mixed.
- Document export permissions, report version selection, and recovery behavior.

## Decisions and review gates

The owner-approved financial rules require explicit currencies, no automatic FX conversion, preservation of unknown legacy values, audited consequential actions, and visible discrepancies. Those decisions do not by themselves define the budget dimension model, approval workflow, version-selection semantics, or migration from existing project/campaign metadata.

Do not use `Project.budget` or `PaidCampaign.budget` as currency-safe financial actual/budget inputs without a reviewed mapping. Do not create variance calculations until both a versioned approved budget contract and a compatible, source-reconciled actuals contract exist.

## Acceptance status

This audit distinguishes existing project/campaign budget fields from a missing dedicated financial budget workflow. It does not add budget entry, versioning, approvals, imports, or variance calculations. Implementation remains gated on an approved budget contract and independently reconciled examples.
