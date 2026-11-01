# Cash forecasting and annual planning

Cash forecasts are versioned planning scenarios. They do not replace ledger, invoice, payment-allocation, or bank-reconciliation records.

## Scenario model

- A forecast groups named scenarios; each scenario has an independently versioned period, currency, opening cash balance, assumptions, and status.
- Scenario versions begin as drafts. Drafts can be edited. Approval makes the scenario and its entries immutable; changes require a new version that copies the prior assumptions and cash-flow entries.
- Generated forecast runs persist an immutable JSON snapshot, reporting cutoff, source transaction IDs, source references, totals, and uncertainty counts. Later source-record changes do not rewrite an earlier run; the same scenario can be rerun to produce a new snapshot.
- Manual entries represent expected inflows or outflows with an expected date, amount, source reference, confidence, and status (`assumed`, `confirmed`, `at_risk`, or `unknown`). Amounts must be positive and use the scenario’s three-letter currency code.
- Forecast periods are limited to 731 calendar days. Dates and scenario assumptions are stored explicitly; no recurring cash-flow rule is inferred.

## Source treatment

- Actual cash movements use non-deleted transactions with a canonical positive `amount_decimal`, an explicit `currency_code`, a timestamp, and a `money_in` or `money_out` direction. Actuals are cut off at the report's `as_of` date.
- Supported issued-customer-invoice receivable balances may be included as projected inflows on the invoice due date. This is a medium-confidence assumption, not a promise of collection. Unresolved or unsupported receivables are excluded. If an invoice has a linked cash transaction that is not confirmed as an allocation to that invoice, its projected receipt is withheld to avoid counting an unmatched payment and invoice balance together. A manual assumption whose source reference matches an automatically projected receivable is also withheld, and duplicate manual source references are rejected within a scenario.
- The current vendor-payable source contract does not provide an approved payable total. Vendor invoice amounts are therefore not forecast automatically. A verified expected supplier payment can be entered as a manual outflow.
- Other currencies remain separate. No foreign-exchange conversion, currency aggregation, or missing-value substitution is performed.
- Manual assumptions dated on or before the report cutoff are excluded from the forward projection. The report exposes unresolved source counts and the basis used for each source class.
- The opening balance is required as an explicit user-entered amount as of the forecast period start; the UI does not default it to zero. It is not inferred from a financial-account balance snapshot.

## Permissions

The Filament page defines `cash-forecast.view`, `cash-forecast.create`, `cash-forecast.update`, and `cash-forecast.approve`. The service checks the same abilities for every mutation and report. UI visibility is supplementary; server-side checks are authoritative.

## Validation

Focused tests:

```bash
php artisan test tests/Feature/CashForecastServiceTest.php
```

The complete local and CI validation requirements remain those in `.chatgpt/osingle.md` and `.github/workflows/tests.yml`.
