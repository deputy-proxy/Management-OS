# Currency and Precision Migration Plan

**Status: implementation plan only; no production row counts have been collected and no migration/backfill is authorized by this document.** The development Sandbox has no approved production-data connection. Counts below must be produced by the read-only audit queries in the approved target environment before the migration plan is considered execution-ready. Do not substitute empty local CI counts for production impact.

**Policy sources:** [Financial Rules Decision Register](financial-rules-decision-register.md), Decisions 1, 2, 4-11, 19-21, 29-35 and 37; [Canonical Financial Data Contract](financial-data-contract.md). Owner-approved policy sets RON as the default for new records, two-decimal monetary precision, half-up rounding at a defined workflow step, explicit non-RON currency, no automatic FX, preservation of historical values, and visible allocation residuals. This does not certify Romanian statutory accounting or tax treatment.

## 1. Scope and affected schema inventory

The following schema fields are in scope because they store monetary values, currency or monetary calculation inputs. “Integer” is the current declared database type, not a conclusion that the value represents whole currency units or minor units.

| Table / fields | Current schema | Currency source today | Main consumer / risk |
| --- | --- | --- | --- |
| transactions.amount | Integer, required | No transaction currency; dashboard uses linked financial account currency | FinancialOverviewData groups/sums by transaction type and account currency. Direction and amount unit are unresolved for legacy rows. |
| customer_invoices.amount_without_vat, vat | Integer, required | No currency field | Invoice form, invoice status dashboard, ProductRevenue relation. Amount unit and total formula are not established; owner policy says no VAT on company invoices, subject to specialist review. |
| vendor_invoices.amount, amount_without_vat, vat | Integer, required | Required string currency | Vendor invoice form and finance overview status counts. Canonical payable total and legacy currency validity are unresolved. |
| taxes.amount | Integer, required | No currency field | Tax form and financial records; amount unit/currency and statutory calculation are not established. |
| financial_account_balances.current_balance | Integer, required | Required currency string on snapshot and account | Snapshot is stored input, not a calculated ledger balance. Unit and currency agreement require source evidence. |
| product_resources.amount | Integer, required | No currency field | ProductCostService uses it as allocation base; unit/currency and historical semantics are unresolved. |
| product_costs.resource_amount, allocated_amount | Integer, required | No currency field | Current allocation formula rounds to integer and recalculation deletes/recreates rows. Historical amounts must not be silently recomputed. |
| product_revenues.amount | Integer, required | No currency field | Links product and customer invoice; no verified currency or equation to invoice total. |
| product_prices.price | Decimal(15,2) after legacy consolidation | Nullable string currency after migration | Product price consumers. Legacy rows may intentionally have unknown currency; preserve provenance. |
| employee_pay_slips.amount | Integer in original migration; later migration changes it to decimal(12,2) | No shared cross-module contract | Legacy baseline/summary is distinct from normalized activity-pay lines. Preserve historical interpretation and do not infer statutory payroll. |
| employee_pay_slip_items.quantity, unit_rate, amount | Decimal columns (quantity 10,2; rate/amount 12,2) | Currency char(3), default RON | Activity-based payroll snapshots. This already uses two-decimal RON activity values but is not a statutory payroll engine. |
| Legacy course/event/workshop price tables | Integer amount; event/workshop currency string; course price has no currency | Inconsistent/absent | Product-price consolidation migration copies source values and leaves unknown currency null. Retain legacy source references; do not fill unknown currency. |

The schema-level inventory is known. The development sandbox has no non-empty application database: `database/database.sqlite` is configured but absent, and the existing `database/ci.sqlite` is a migrated CI schema with no financial rows. The read-only audit below records that measured local state; it is not a representative estimate of a populated deployment. No production values should be copied into test fixtures.

### 2.0 Local test-database audit evidence

Read-only inspection was performed on 2026-10-24 against `database/ci.sqlite` using SQLite `mode=ro`; no writes or seed operations were run. The database contained 109 tables. Every existing table in scope had zero rows, zero non-null monetary values, and therefore no currency/status/type values or before/after totals to reconcile. `database/database.sqlite` was not present.

| Table | Rows | Monetary values / total |
| --- | ---: | --- |
| `transactions` | 0 | `amount`: no values; sum unavailable |
| `customer_invoices` | 0 | `amount_without_vat`, `vat`: no values; sums unavailable |
| `vendor_invoices` | 0 | `amount`, `amount_without_vat`, `vat`: no values; sums unavailable |
| `taxes` | 0 | `amount`: no values; sum unavailable |
| `financial_accounts` | 0 | currency/status inventory empty |
| `financial_account_balances` | 0 | `current_balance`: no values; sum unavailable |
| `product_resources` | 0 | `amount`: no values; sum unavailable |
| `product_costs` | 0 | `resource_amount`, `allocated_amount`: no values; sums unavailable |
| `product_revenues` | 0 | `amount`: no values; sum unavailable |
| `product_prices` | 0 | `price`: no values; sum unavailable; currency inventory empty |
| `employee_pay_slips` | 0 | `amount`: no values; sum unavailable |
| `employee_pay_slip_items` | 0 | `quantity`, `unit_rate`, `amount`: no values; sums unavailable |
| Legacy `course_prices`, `event_prices`, `workshop_prices` | 0 each | no monetary values; sums unavailable |

This establishes **0 affected rows in the available local CI database only**. It does not establish zero affected rows in any other environment. Since there are no rows, the local database cannot demonstrate legacy value distributions, conversion factors, ambiguity rates, or meaningful before/after reconciliation. The read-only queries below remain required against the populated test dataset designated by the project owner; attach aggregate-only results from that dataset before treating impact as representative.

## 2. Read-only impact audit

Run these queries against a controlled, read-only database connection after confirming the schema version. Store only the timestamp, database/schema version, query version, table-level counts and aggregate totals in the audit evidence. Do not export customer/vendor names, invoice documents, descriptions or raw transaction rows.

### 2.1 Row counts for monetary tables

MySQL 8 query; adjust only if a table is absent in the specific environment and record the schema difference.

```sql
SELECT 'transactions' AS table_name, COUNT(*) AS total_rows,
       SUM(deleted_at IS NULL) AS active_rows,
       SUM(deleted_at IS NOT NULL) AS soft_deleted_rows
FROM transactions
UNION ALL
SELECT 'customer_invoices', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM customer_invoices
UNION ALL
SELECT 'vendor_invoices', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM vendor_invoices
UNION ALL
SELECT 'taxes', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM taxes
UNION ALL
SELECT 'financial_account_balances', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM financial_account_balances
UNION ALL
SELECT 'product_resources', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM product_resources
UNION ALL
SELECT 'product_costs', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM product_costs
UNION ALL
SELECT 'product_revenues', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM product_revenues
UNION ALL
SELECT 'product_prices', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM product_prices
UNION ALL
SELECT 'employee_pay_slips', COUNT(*),
       SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL)
FROM employee_pay_slips
UNION ALL
SELECT 'employee_pay_slip_items', COUNT(*), COUNT(*), 0
FROM employee_pay_slip_items;
```

This provides table-level exposure, not proof that every row requires conversion. Retain active and soft-deleted counts separately; historical rows remain part of the audit and must not be dropped from the reconciliation scope.

### 2.2 Currency, status and type inventories

Run aggregate-only inventories. Preserve unknown values in the output; do not normalize them in place.

```sql
SELECT 'financial_accounts.currency' AS field_name, currency AS stored_value,
       COUNT(*) AS row_count
FROM financial_accounts GROUP BY currency
UNION ALL
SELECT 'financial_account_balances.currency', currency, COUNT(*)
FROM financial_account_balances GROUP BY currency
UNION ALL
SELECT 'vendor_invoices.currency', currency, COUNT(*)
FROM vendor_invoices GROUP BY currency
UNION ALL
SELECT 'product_prices.currency', COALESCE(currency, '<NULL>'), COUNT(*)
FROM product_prices GROUP BY currency;
```

```sql
SELECT 'transactions.transaction_type' AS field_name, transaction_type AS stored_value,
       COUNT(*) AS row_count
FROM transactions GROUP BY transaction_type
UNION ALL
SELECT 'customer_invoices.status', status, COUNT(*)
FROM customer_invoices GROUP BY status
UNION ALL
SELECT 'vendor_invoices.status', status, COUNT(*)
FROM vendor_invoices GROUP BY status
UNION ALL
SELECT 'taxes.status', status, COUNT(*)
FROM taxes GROUP BY status;
```

For each result, classify exact values as approved mapping, known legacy alias, unknown or conflicting. Preserve the original stored value and mapping evidence. A row count is not permission to update the row.

### 2.3 Range, precision and relationship diagnostics

```sql
SELECT 'transactions.amount' AS field_name, COUNT(*) AS rows_total,
       SUM(amount < 0) AS negative_rows,
       MIN(amount) AS min_value, MAX(amount) AS max_value
FROM transactions
UNION ALL
SELECT 'customer_invoices.amount_without_vat', COUNT(*),
       SUM(amount_without_vat < 0), MIN(amount_without_vat), MAX(amount_without_vat)
FROM customer_invoices
UNION ALL
SELECT 'customer_invoices.vat', COUNT(*),
       SUM(vat < 0), MIN(vat), MAX(vat)
FROM customer_invoices
UNION ALL
SELECT 'vendor_invoices.amount', COUNT(*),
       SUM(amount < 0), MIN(amount), MAX(amount)
FROM vendor_invoices
UNION ALL
SELECT 'vendor_invoices.amount_without_vat', COUNT(*),
       SUM(amount_without_vat < 0), MIN(amount_without_vat), MAX(amount_without_vat)
FROM vendor_invoices
UNION ALL
SELECT 'vendor_invoices.vat', COUNT(*),
       SUM(vat < 0), MIN(vat), MAX(vat)
FROM vendor_invoices;
```

This diagnostic reports values as stored. It does not assert whether negative legacy invoice amounts are valid corrections or malformed data; those require reviewed classification. Repeat equivalent range queries for taxes.amount, financial_account_balances.current_balance, product_resources.amount, product_costs.resource_amount/allocated_amount, product_revenues.amount and product_prices.price.

Before choosing target column precision, inspect maxima, minima and decimal scale for every decimal source, and test target bounds against the aggregate results. Do not select a target precision based only on the current sample or development database.

## 3. Target representation and conversion policy

- New monetary fields should use fixed-precision decimal storage with two decimal places, sized after the range audit; the provisional design candidate is DECIMAL(19,2), subject to observed maxima and schema-owner approval.
- Currency uses a normalized ISO 4217 code for new records. RON is the default only for new workflows whose policy specifies it. Legacy currency is not inferred. Currency remains nullable/unknown where source evidence cannot establish it.
- Do not automatically convert FX. Cross-currency workflows must preserve each side's amount/currency and explicit approved exchange-rate evidence.
- Direction is separate from transaction amount. The target transaction amount is non-negative; refunds/reversals are separate linked events.
- Invoice totals use higher intermediate calculation precision and round the final total at the defined workflow step. The exact field formula and statutory tax semantics require approval before code implements them.
- Product allocation percentages must total exactly 100%; residuals stay visible and flagged, not silently distributed.
- Preserve all original values, source IDs, status/type text, soft-delete state, timestamps and audit history. Unknown or inconsistent records are quarantined from unqualified totals rather than guessed.
- Do not transform employee payslip baselines or activity-pay snapshots as part of a generic finance amount migration. Payroll has its own compatibility and specialist-review gates.

The integer-to-decimal conversion factor is **not yet defined per field**. For a legacy integer value, do not assume either major currency units or minor units. A mapping requires source-document evidence and owner approval. If unit evidence is unavailable, retain the original value, mark the canonical value unresolved, and do not backfill it.

## 3.1 Additive schema stage implemented in #308

The #308 implementation adds nullable canonical storage alongside legacy values. It deliberately does not select or convert legacy units, infer currencies, change existing required fields, or switch existing readers/writers. The current columns remain the compatibility source until a separate mapping and consumer migration is approved.

| Table | Additive nullable fields |
| --- | --- |
| `transactions` | `amount_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |
| `customer_invoices` | `amount_without_vat_decimal DECIMAL(19,2)`, `vat_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |
| `vendor_invoices` | `amount_decimal DECIMAL(19,2)`, `amount_without_vat_decimal DECIMAL(19,2)`, `vat_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |
| `taxes` | `amount_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |
| `financial_account_balances` | `current_balance_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |
| `product_resources` | `amount_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |
| `product_costs` | `resource_amount_decimal DECIMAL(19,2)`, `allocated_amount_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |
| `product_revenues` | `amount_decimal DECIMAL(19,2)`, `currency_code CHAR(3)` |

`DECIMAL(19,2)` is the provisional precision candidate from this plan, not a validated maximum-range guarantee. The additive columns are nullable so old rows remain valid and unknown legacy values stay unknown. The three-character currency field is a storage shape only; canonical ISO 4217 validation and writer enforcement are not enabled by this migration. `product_prices` already has decimal storage and nullable legacy currency after its earlier consolidation, so this migration does not alter it. Activity-pay/payslip tables remain outside this generic finance migration.

The migration refuses to roll back if any new canonical field contains a value, rather than silently deleting data. It is additive and has no backfill. Existing models expose the new fields with two-decimal casts and activity-log coverage where the model already records changes; no existing forms, dashboard aggregation, reports, or calculations were switched to the new columns in this issue. Those consumers must be migrated in a later reviewed step after range and field-mapping evidence.

## 4. Staged rollout

1. **Audit-only baseline:** capture schema version, table/active/soft-deleted counts, distinct currency/type/status values, amount ranges, relationship anomalies, and per-period totals grouped by verified currency/status. Sign off the report before writing any migration.
2. **Mapping approval:** for each field, document old unit, target unit, currency source, nullability, rounding, valid ranges, status/type mappings, source evidence and disposition of ambiguous rows. Record owner and qualified specialist approval where applicable.
3. **Additive schema stage (#308):** add canonical decimal/currency/reference fields without dropping or rewriting legacy fields. Keep old writers/readers working during deployment. Exact columns and constraints are approved in #308, not this plan.
4. **Deterministic backfill:** only map rows whose unit/currency/status meaning is approved. Use stable primary-key batches, bounded transactions, resumable checkpoints, dry-run output and idempotent updates. Record migrated, unchanged, rejected and quarantined counts. Preserve raw source values and source references.
5. **Compatibility release:** update writers to validate new records against the canonical contract. Keep readers able to interpret both representations during rollout; no automatic FX or silent rounding. Use explicit feature/configuration gates for switching reads.
6. **Reconciliation gate:** compare before/after counts and totals by table, period, verified currency, status and soft-delete state. Independently review every quarantined class and any difference. A difference must be explained and approved, not hidden by a tolerance.
7. **Read switch and observation:** switch each reader only after reconciliation approval. Monitor mismatch/quarantine counts and operational errors. Keep legacy columns read-only and available for recovery for the agreed retention window.
8. **Retirement:** remove legacy columns only in a later approved issue after all consumers are proven migrated, backups/recovery are tested, and historic values remain traceable. This issue does not authorize retirement.

## 5. Reconciliation and acceptance evidence

The execution record must include:

- Audit run timestamp, environment/schema version, script revision and authorized operator.
- Counts for all scoped tables split into active/soft-deleted rows, plus rows classified as mapped, unchanged, rejected and quarantined.
- Before/after totals by table and period, split by verified currency and status; do not combine currencies or invent a currency for unknown records.
- Range/precision reports, distinct currency/type/status inventories and relationship-integrity findings.
- Row-level internal identifiers for exceptions in the controlled audit system only; no sensitive values in ordinary logs or test fixtures.
- Independent reviewer approval of mappings, total differences, quarantines, rounding behavior and recovery plan.
- Evidence of a tested restore/forward-fix procedure and verification that restored totals and audit links match the pre-migration baseline.

**Current impact status:** schema fields are inventoried above; real affected-row counts and before/after totals are pending the approved read-only audit. This plan must not be represented as quantified or execution-ready until those results are attached.

## 6. Required tests before any backfill is enabled

Use synthetic/sanitized fixtures only. The implementation PR must test:

- Exact mappings for approved old units and currency values, including explicit RON and a non-RON code.
- Unknown/null currency and ambiguous legacy unit remain unchanged and are quarantined.
- Decimal values at zero, two-decimal boundaries, maximum supported precision and values exceeding target range.
- Half-up rounding at the approved calculation stage; no silent line-by-line rounding or residual distribution where policy says final-total rounding.
- Negative transaction amounts rejected in the canonical writer; refunds/reversals preserve original and link correctly.
- Unknown statuses/types preserved and reported; approved mappings are deterministic and idempotent.
- Soft-deleted rows remain represented in audit/reconciliation and are not accidentally restored or discarded.
- Repeated dry-run/backfill is idempotent; batch interruption/resume does not duplicate effects.
- Before/after count and total reconciliation, mismatched invoices, orphan links, unknown currency, duplicate identities and quarantined rows.
- Permission failures, audit events, sensitive-data-safe output, transaction rollback, recovery and read-only audit mode.
- SQLite migration/test coverage plus MySQL 8.0 migration and database-sensitive regression coverage; verify the actual staging MySQL minor version before production release.

## 7. Approval gates and sequencing

| Gate | Required evidence | Status |
| --- | --- | --- |
| Owner operational policy | Decisions 1-11 and related decisions in the financial rules decision register | Recorded; does not constitute statutory specialist approval. |
| Canonical field mapping | [#306 contract](financial-data-contract.md), field meanings, unknown-value treatment and consumer inventory | Draft merged; per-field legacy conversion mapping still needs evidence. |
| Qualified Romanian accounting/tax review | Applicable invoice/tax interpretations and independently checked examples | Required before applicable fiscal behavior or conversion is approved. |
| Read-only data audit | Counts, value inventories, ranges, totals and relationship anomalies from a populated designated test environment | Local CI audit recorded 0 rows; representative populated test-dataset audit pending. |
| Migration approval | Deterministic mapping, staged rollout, before/after reconciliation, quarantine and tested recovery | Additive schema is implemented; mapping, backfill and reader switch remain pending audit/review. |
| Schema implementation | Approved field design and migration plan; tests and CI green | #308 implements additive nullable storage only; legacy columns and consumers remain unchanged, with no backfill. |

## 8. Next steps

1. Obtain authorization to run the aggregate-only audit against the designated staging/production-like database and attach the result counts/totals.
2. Resolve legacy units and currency/status mappings with source evidence; keep unknowns quarantined.
3. Update this plan with actual counts and reviewed before/after expectations, then record the accountable approvals.
4. Before any backfill or reader switch, complete the populated test-dataset audit, resolve field mappings, and obtain the applicable reviews. The #308 schema stage performs no amount scaling, currency fill or historical backfill.
