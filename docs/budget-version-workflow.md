# Versioned budget workflow

Management-OS stores budgets in a dedicated ledger. The legacy integer `projects.budget` and `paid_campaigns.budget` fields remain unchanged and are not imported, inferred, or treated as canonical budget values.

## Data contract

- A budget is a named record with one or more immutable historical versions.
- Each version stores an inclusive period (`period_start` through `period_end`), a three-letter uppercase currency code, a normalized dimension key/value map, and its own line items.
- Line-item amounts are non-negative decimal amounts with two fractional digits. They use the parent version currency; currencies are never summed or converted by this workflow.
- A line item may repeat a subset of the budget dimensions, but every supplied key/value must exactly match the parent dimension contract.
- Empty dimensions are supported. The application does not invent missing dimensions or default business classifications.

## Lifecycle

`draft → submitted → approved → retired`

- Draft versions can be edited. Editing replaces the draft's line items inside a transaction and appends an audit snapshot.
- Submission validates the period, currency, dimension contract, line items, and overlap against other submitted or approved budgets.
- Approval repeats the overlap check and records the approving actor and timestamp.
- Submitted and approved version contents are immutable. Corrections require a new draft version.
- Retirement is a lifecycle event, not deletion. Retired versions and their line items remain available for historical review.
- Each create, edit, submit, approve, and retire operation appends an immutable event containing a complete version snapshot.

## Period overlap

Periods are inclusive. Two submitted/approved versions conflict when their periods intersect and their normalized dimension maps are identical. A conflicting budget is rejected even if its currency differs, preventing parallel competing budgets for the same scope. Different dimension scopes may have overlapping periods. Retired versions no longer reserve a period.

A new version within the same budget is treated as a revision of that budget and is not compared against its own prior versions.

## Permissions

The Filament resource defines these HexaLite permissions:

- `budget.index`: view budgets and history
- `budget.create`: create a budget and its first draft version
- `budget.update`: edit a draft or create a subsequent version
- `budget.submit`: submit a draft for approval
- `budget.approve`: approve a submitted version
- `budget.retire`: retire an approved version

The domain service enforces these permissions independently of UI visibility.

## Validation

Run the focused workflow regression suite with:

```sh
php artisan test tests/Feature/BudgetVersionWorkflowTest.php
```

The full CI workflow remains authoritative before merging. No real budget values, owner-approved allocation defaults, or legacy budget backfills are included in this implementation.
