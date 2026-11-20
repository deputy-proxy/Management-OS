# MCP finance tools

Issue #534 adds nine read-only finance tools to the Streamable HTTP MCP server. The handlers reuse existing Management-OS finance reporting services. They do not create invoices, write transactions, approve allocations, generate forecast runs, persist budget comparison snapshots, calculate profitability runs, or make reconciliation decisions.

## Authorization

Every tool requires the authenticated AI principal, mcp:use, its dedicated OAuth scope, and the required HexaLite permissions in config/mcp.php. The AI principal receives no finance permissions automatically. Operators must explicitly grant only reviewed permissions.

| Tool | Required permissions | Optional source permissions |
| --- | --- | --- |
| get_financial_overview | finance-dashboard.view | transaction.index, customer_invoice.index, vendor_invoice.index |
| get_transactions | transaction.index | financial_account.index |
| get_receivables | receivables-payables.view, customer_invoice.index | transaction.index |
| get_payables | receivables-payables.view, vendor_invoice.index | transaction.index |
| get_cash_forecast | cash-forecast.view, transaction.index, customer_invoice.index, vendor_invoice.index | None |
| get_budget_vs_actuals | budget.actuals.view, budget.index, transaction.index | None |
| get_profitability_report | finance-profitability.view | product.index |
| get_reconciliation_status | financial-reconciliation.view | transaction.index, financial_account.index |
| get_invoice_details | receivables-payables.view | customer_invoice.index, vendor_invoice.index, transaction.index |

Optional source permissions are checked independently. Missing permission means the related section is empty or explicitly unavailable, never an inferred zero. Payment/allocation details require transaction.index; account labels and account filtering require financial_account.index; invoice details require the source-specific invoice permission.

## Tool contracts

| Tool | Inputs and limits | Behavior |
| --- | --- | --- |
| get_financial_overview | Empty object | Current and previous reporting-month summary. Legacy integer totals are labelled legacy_integer_unverified; canonical cash movements remain separated by currency and direction. Invoice status counts are independently permission-filtered. |
| get_transactions | Required start_date, end_date; optional currency_code, direction, category, account_id, limit (1–100, default 50), offset (0–1,000). Maximum range: 90 days. | Canonical cash movements only. No counterparty, source ID, source URL or raw description. Unknown amount/currency stays null/unknown. |
| get_receivables | Optional as_of; limit (1–100, default 50), offset (0–1,000). | Issued customer invoice receivables plus unresolved legacy records. Legacy amounts remain null. Payment/allocation details require transaction.index. |
| get_payables | Optional as_of; limit (1–100, default 50), offset (0–1,000). | Vendor invoice metadata and supported payment state. Vendor payable totals remain unavailable because the current source contract does not approve them. |
| get_cash_forecast | Required scenario_id; optional as_of; limit (1–366, default 100), offset (0–730). | Reads approved or retired scenarios only. Actual and projected flows remain separate. Does not generate a forecast run or expose free-text assumptions, transaction IDs or source references. |
| get_budget_vs_actuals | Required budget_version_id; limit (1–100, default 50), offset (0–1,000). | Reads approved or retired budget versions through a non-persisting preview. Does not create a BudgetActualReportRun. Unsupported classifications and currency mismatches remain excluded; no FX conversion is applied. |
| get_profitability_report | Optional run_id, currency_code, product_id; limit (1–100, default 50), offset (0–1,000). | Reads a completed immutable run. Currency totals remain separate. Product rows require product.index; customer-level details are never returned. |
| get_reconciliation_status | Required start_date, end_date; optional account_id, currency_code, decision; limit (1–100, default 50), offset (0–1,000). Maximum range: 90 days. | Reports statement status and candidate suggestions only. It does not accept or reject matches. Candidate detail requires transaction.index; account labels require financial_account.index. |
| get_invoice_details | Required invoice_kind (issued_customer_invoice, legacy_customer_invoice, or vendor_invoice) and source_id; optional as_of. | Reads a narrow safe invoice metadata record. Party identity, internal IDs, document paths, line descriptions and allocation history are excluded. |

All date and as-of reporting uses Europe/Bucharest. Each result includes period/as-of context and currency/unit or source-status metadata where applicable. Runtime field allowlists in config/mcp.php are authoritative.

## Data boundaries and known limitations

- Currency is never collapsed. Amounts remain grouped by source currency. No exchange rate or cross-currency total is inferred.
- Unknown is not zero. Missing, unresolved, unsupported or unapproved amounts remain null or explicitly unavailable. Excluded counts are distinct from excluded amounts.
- Legacy transaction units are unverified. Legacy integer totals are labelled legacy_integer_unverified; they are not presented as decimal currency values.
- Receivables and payables are asymmetric. Supported issued customer invoice balances may be returned. Legacy customer invoice amounts remain unresolved. Vendor payable totals remain unavailable; payment allocations do not establish the payable total.
- Actuals and forecasts remain separate. Forecast output includes source coverage counts but excludes free-text assumptions and source identifiers.
- Budget comparisons are previews. MCP uses BudgetActualsComparisonService::preview, not the UI's persisted compare path. Preview does not create a report-run record and can omit source transaction IDs.
- Profitability is read from stored runs. MCP never calculates a new run. Product rows require product permission; customer details are excluded; project attribution remains unavailable when its source mapping is incomplete.
- Reconciliation candidates are suggestions. Candidate matches are not approvals and are never automatically accepted or rejected.
- Invoice details are deliberately narrow. No party names, postal addresses, tax identifiers, raw descriptions, internal IDs, allocation history or artifact URLs are returned.

## Implementation sources

- FinancialOverviewData supplies overview aggregates.
- ConsolidatedFinancialReportData supplies cash-movement reporting.
- ReceivablesPayablesData supplies receivable/payable and invoice status data.
- CashForecastService::report reads approved/retired scenarios; generateRun is not called.
- BudgetActualsComparisonService::preview shares the comparison calculation path without persisting a report run. The UI compare method retains persisted snapshot behavior.
- ProfitabilityReportService::report reads completed immutable runs with customer details disabled.
- FinancialReconciliationReportData supplies statement status and candidate suggestions.

Regression coverage is in tests/Feature/McpFinanceToolsTest.php, tests/Feature/McpTransportTest.php, tests/Feature/BudgetActualsComparisonServiceTest.php and existing finance service suites. The MySQL workflow includes MCP finance and budget preview tests.
