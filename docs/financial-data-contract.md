# Canonical Financial Data Contract

**Status: technical contract draft based on the company-owner decisions recorded in the [financial rules decision register](financial-rules-decision-register.md).** This document defines operational field meaning and compatibility boundaries. It is not statutory accounting/tax advice, does not claim independent specialist approval, and does not authorize schema changes or historical backfills.

**Decision precedence:** recorded owner policy describes intended behavior; existing code describes observed legacy behavior. Where they conflict, do not silently alter data or runtime behavior. Where qualified Romanian accounting/tax/payroll review is required, the relevant implementation remains blocked until that review is recorded.

## 1. Canonical money representation

| Property | Intended contract | Compatibility rule |
| --- | --- | --- |
| Amount | Non-negative monetary magnitude; transaction direction is separate. | Current integer columns do not prove whether values are whole currency units or minor units. Do not rescale until each field's legacy unit is evidenced. |
| Currency | ISO 4217 code for new records. RON is the default where owner policy says so; other currencies must be explicit. | Unknown legacy currency remains unknown. Do not infer currency from location or an account link. |
| Precision | Monetary values stored/presented to two decimals under owner policy; invoice calculations may use higher intermediate precision. | Persisted precision and intermediate precision must be distinguished. No conversion or migration until legacy units are audited. |
| Rounding | Half-up rounding at a named workflow step; invoice totals round at the final-total step. | Test intermediate versus persisted precision. Allocation residuals remain visible and flagged, never silently redistributed. |
| FX | No automatic conversion; report currencies separately. Cross-currency transfers record both amounts and the applied rate explicitly. | Never invent an exchange rate or infer a rate source/date. |
| Unknown/discrepant values | Preserve source values and flag for review. | Do not silently coerce, normalize, omit, recalculate, or overwrite historical values. Qualify reports affected by unknowns. |

These are operational owner-approved rules, not a statement of statutory compliance.

## 2. Field-level contract and compatibility map

Current types below describe migrations in the repository, not a guarantee that production rows are valid. Some legacy tables store IDs without database foreign-key constraints.

| Entity and fields | Current storage/source | Intended meaning and validation | Consumers, compatibility, unresolved points |
| --- | --- | --- | --- |
| Financial accounts: currency | Required string on financial_accounts. | Currency code for the account. | FinancialOverviewData groups transaction sums by this field; it does not prove the unit/currency of legacy transaction amounts. Validate existing values before any code list constraint. |
| Account balance: current_balance, currency, reported_on | Required integer, string and date. | Dated reported balance snapshot, not a calculated ledger balance. | UI and audit tooling read it. Integer unit and agreement with account currency require source evidence; preserve discrepant snapshots. |
| Transaction: amount, transaction_type, payment_method, timestamp | Required integer, strings and datetime. | Target: non-negative amount, separate money-in/money-out direction, type as business classification, payment method as metadata. | Form currently offers Customer, Vendor, Tax and Other. Dashboard groups by stored type and account currency. Legacy amount units, direction and allowed types remain to be mapped. |
| Transaction: financial_account_id | Required account ID; Eloquent relationship. | Account affected by transaction. | No transaction currency field. Do not infer missing legacy currency from account. Check orphan references. |
| Transaction: customer_invoice_id, vendor_invoice_id, tax_id | Current form requires customer invoice and tax; vendor invoice is optional; model allows all three. | References populated according to an approved transaction workflow. | Current schema permits ambiguous combinations and does not encode transfer, fee, refund, reversal or settlement semantics. Link alone is not proof of payment. Review form-required versus legacy nullability. |
| Transaction: timestamp | Required datetime. | Event timestamp with an explicitly defined timezone/meaning. | Dashboard uses it for monthly grouping. Reporting boundaries use Europe/Bucharest; preserve separate invoice, due and payment dates. |
| Customer invoice: amount_without_vat, vat | Required integer fields; no currency field. Form labels them net amount and VAT amount. | Target: explicit amount units, currency and canonical receivable total. Owner policy states company invoices will not have VAT and no rate should be inferred. | Labels do not establish stored units or total formula. Preserve history; do not infer currency, apply VAT or recalculate. Qualified Romanian tax/invoicing review remains required. |
| Customer invoice: status | Required free-text string; current options Draft, Sent, Paid, Overdue. | Target: explicit lifecycle state; Paid requires authorized settlement confirmation. | Preserve original status text and map legacy values through a reviewed mapping. A transaction link or zero balance does not prove settlement. |
| Customer invoice: month | Initially string; dashboard compares it as date-like month. | Billing/reporting period, distinct from issue, due, service and payment dates. | Validate legacy values before date comparisons or type changes. |
| Customer invoice: customer_id, customer_contract_id, invoice, identifier | Required IDs and document reference; identifier added later. | Source relationships, invoice document and business identifier. | Identifier uniqueness, numbering, credit-note linkage and correction lifecycle are not enforced by current schema. Do not conflate this record with a future canonical issued-invoice/e-Factura model. |
| Vendor invoice: amount, amount_without_vat, vat | Required integer fields; amount added later. | Target: one explicitly designated canonical payable total; other amount fields validated against defined meanings. | Authoritative field cannot be inferred from names or values. Preserve and flag inconsistencies; do not choose a total or recalculate automatically. Specialist review remains required. |
| Vendor invoice: currency | Required string. | Explicit currency for new invoices; RON default under owner policy unless another currency is selected. | Legacy values may be missing or unreliable. Unknown legacy currency stays unknown until verified against source documents. |
| Vendor invoice: status | Required free-text string. | Target: explicit lifecycle and settlement state; paid requires authorized confirmation. | No canonical state machine in the observed form. Preserve originals and surface unknown values. |
| Vendor invoice: month | Originally string, changed to date in later migration; model casts to date. | Billing/reporting period, not issue, due, received or paid date. | Validate legacy parseability and downstream use. |
| Vendor invoice: vendor_id, vendor_contract_id, invoice, invoice_number, identifier | IDs and multiple document/reference strings. | Vendor/contract sources and document identifiers. | Uniqueness, external ID scope, correction links and canonical invoice lifecycle need explicit rules. |
| Tax: amount, category, month, status, due_on, paid_on | Required integer, strings and dates; no currency column. Current categories vat/labor; statuses pending/paid/overdue. | Operational tax-obligation record; explicit amount unit/currency and category; due date distinct from paid date. | No statutory calculation is established. Do not infer currency or merge tax status with invoice settlement. |
| Product cost: resource_amount, allocated_amount, allocation_percent, month | Required integer amounts/percentage and date; no currency field. | Resource cost and allocated portion with explicit currency, approved percentage basis and visible residual. Percentages total exactly 100% under owner policy. | Current formula/rounding is implementation behavior, not policy. Preserve historic allocations; no silent recalculation or residual redistribution. |
| Product revenue: amount, product_id, customer_invoice_id, month | Required integer amount, links and date; no currency field. | Revenue amount with explicit units/currency and defined relationship to invoice total. | Do not assume it equals invoice total or aggregate it with other amounts until mapped. |
| Product price: price, currency, priceable reference, valid_from, valid_until | Decimal price; currency nullable after legacy unification; polymorphic source and effective dates. | Price and explicit currency for new records, with effective period/source traceability. | Legacy migration intentionally permits null currency. Keep unknown as unknown; do not fill with RON without evidence. |
| Payslip line: quantity, unit_rate, amount, currency | Decimal values, three-character currency default RON, calculation snapshot and source keys. | Existing activity-pay workflow uses RON and two-decimal half-up calculations with immutable source snapshots. | Activity pay is not statutory payroll. Do not infer deductions or apply this contract automatically to every finance table. |

Evidence includes app/Models/Transaction.php, CustomerInvoice.php, VendorInvoice.php, Tax.php, FinancialAccount.php, FinancialAccountBalance.php, ProductCost.php, ProductRevenue.php, ProductPrice.php and EmployeePaySlipItem.php; related migrations in database/migrations; forms under app/Filament/Resources; app/Services/FinancialOverviewData.php, ProductCostService.php and ProductsOverviewData.php; app/Console/Commands/AuditFinanceReconciliation.php and associated feature tests.

### Metadata and relationship fields

| Field group | Contract meaning | Current behavior / compatibility |
| --- | --- | --- |
| financial_accounts.id, title, type, bank, status, opened_on, closed_on | Stable identity; display name; account classification; optional institution; lifecycle status; optional opening/closing dates. | Title/type/status are free text; bank/open/close dates may be nullable. Allowed values, close rules and relationship to archived balances need a reviewed vocabulary. Soft deletion is present. |
| financial_account_balances.id, financial_account_id, currency, reported_on | Snapshot identity, account association, snapshot currency and as-of date. | current_balance is integer and there is no enforced DB foreign key in the initial migration. Audit orphan and currency mismatches; do not infer amount unit. |
| transactions.id, timestamps, deleted_at | Stable transaction identity, record timestamps and soft-deletion marker. | Created/updated timestamps are metadata, not event timestamp. Soft deletion does not mean reversal; preserve audit history and use linked reversal/refund events under target contract. |
| customer/vendor invoice id, created_at, updated_at, deleted_at | Internal identity and record lifecycle metadata. | Soft-deleted invoices remain historical evidence. Do not use deletion as a credit note, void, refund or correction. |
| taxes.id, timestamps, deleted_at | Internal obligation identity and record metadata. | No separate transaction lifecycle or currency is present; do not infer statutory obligation state from timestamps. |
| product_costs.product_id, product_resource_id, month, allocation_percent, resource_amount, allocated_amount, id/timestamps/deleted_at | Source resource, destination product, allocation period, percentage and recorded input/output amounts. | Current code calculates allocated_amount as rounded resource_amount × allocation_percent / 100, then deletes and recreates existing rows. This is observed legacy behavior, not approved policy; percentage sums and safe correction need future implementation. |
| product_revenues.product_id, customer_invoice_id, month, id/timestamps/deleted_at | Product and invoice association, operational revenue period and record metadata. | Amount has no currency and its relation to invoice totals is not enforced. Preserve source links and avoid double-counting. |
| product_prices.priceable_type/id, price_type, valid_from/until, legacy_source_type/id, id/timestamps/deleted_at | Polymorphic price target, price category, effective interval and legacy migration provenance. | Current price has two-decimal cast; currency may be null for migrated legacy rows. Keep source provenance and unknown currency. |
| employee_pay_slip_items.source_type/id/key, period_key, occurred_on, description, quantity, unit, unit_rate, amount, currency, calculation_snapshot | Source-linked activity-pay line with period, source occurrence, calculation details and immutable snapshot. | Unique source keys and employee/source identity prevent duplicates; line currency defaults to RON. These fields do not implement statutory deductions, tax or payroll reporting. |
| payroll_calculation_runs.employee_id, employee_contract_id, employee_contract_payroll_input_id, payroll_policy_id, employee_pay_slip_id, period, idempotency_key, request_fingerprint, status, input/policy snapshots | Immutable versioned record of explicit contract/input/policy references for one complete month. | Missing or incomplete facts block calculation; legacy contract salary/rate are context only and never inferred as inputs. |
| payroll_calculation_results.run_id, result_status, currency, nullable gross/deductions/net, result_snapshot, snapshot_hash | One immutable hash-identified result state per calculation run. | Blocked and not-calculated results have null totals; successful totals are produced only by explicitly configured arithmetic. |
| payroll_calculation_result_items.result_id, line_number, item_code/type, calculation_method, input_value, rate_percent, amount, currency, rounding, calculation_snapshot | Immutable ordered line-level arithmetic and formula evidence. | Unique line numbers and codes per result; no FX or statutory rates are supplied by the engine. |
| employee_contract_payroll_inputs.base_amount | Explicit effective-dated amount for the configured salary basis. | Nullable for legacy rows; never backfilled from `EmployeeContract.base_salary` or `hourly_rate`. Missing explicit inputs block calculation. |


### Representative contract examples (specification only)

| Input case | Expected contract outcome | Why |
| --- | --- | --- |
| New monetary record with amount 125.50 and currency RON | Accept only when workflow field meaning and validation are defined; preserve amount as 125.50. | RON default and two-decimal policy do not define every table's unit. |
| Legacy transaction amount 125 with no transaction currency | Mark currency/unit unknown until source evidence establishes both; exclude or qualify affected currency totals. | Integer value and account link do not prove whether 125 means major/minor units or prove historical currency. |
| Negative transaction amount | Reject under target contract; use explicit money-in/money-out direction. | Direction is separate from non-negative magnitude. |
| Invoice linked to transaction whose amount differs from outstanding balance | Flag mismatch; do not mark invoice paid automatically. | Settlement requires deterministic preview and explicit authorized confirmation. |
| Vendor invoice fields amount=120, amount_without_vat=100, vat=15 | Preserve all source values and flag inconsistency; do not choose or recalculate a total. | Canonical payable field and tax treatment must be resolved by reviewed rules. |
| Legacy product price with null currency | Preserve unknown currency and source provenance. | Migration intentionally allows null currency for legacy prices; no automatic RON fill. |
| Product allocations total 99% or 101% | Reject/flag allocation before applying under target policy; do not silently normalize or distribute residual. | Approved overhead allocation percentages must total exactly 100%. |
| Retry of an already applied payment/import | Idempotent result with no duplicate financial effect and an auditable attempt. | Retries must not create duplicate financial events. |

## 3. Transaction vocabulary and lifecycle

**Implemented subset:** [`transaction-classification-v1.md`](transaction-classification-v1.md) adds explicit `money_in` / `money_out` direction for new form entries and a count-only classification view. Legacy rows without direction remain unclassified. This does not authorize legacy backfill, amount scaling, settlement inference, or transfer/refund/reversal posting.


Owner-approved target behavior:

- Direction is explicit money-in or money-out; amount is non-negative.
- Transaction type is distinct from direction. Payment method remains metadata.
- Refunds and reversals are separate linked events; preserve the original event.
- Transfers between company accounts use a transfer record and linked account entries. Cross-currency transfers preserve both amounts and the explicit rate. Fees are separate records linked to the principal/transfer to avoid double-counting.
- Supported workflows distinguish customer receipt, vendor payment, tax payment, other money-in/out, transfer, fee, refund and reversal. Exact storage vocabulary and allowed reference combinations must be reviewed before implementation.
- Lifecycle states distinguish recorded, confirmed, reversed and refunded as applicable. Allowed transitions, actor permissions, evidence and audit events must be specified before runtime changes.
- The approved payment workflow is one full-balance payment. Preview deterministic allocation, reject over-allocation, flag mismatches and require explicit authorized confirmation before marking an invoice paid.
- Idempotency prevents retries creating duplicate financial effects. Possible duplicates are preserved and flagged, not automatically merged.

**Compatibility rule:** current UI labels Customer, Vendor, Tax and Other are legacy classifications, not a complete canonical direction/status vocabulary. Any future mapping must preserve the original value, record the mapped value and rationale, and quarantine unknown/conflicting combinations. This documentation does not execute a mapping.

## 4. Invoice, tax and product totals

- Every amount field needs declared unit, currency, precision, nullability, validation range and relationship to a canonical total.
- The owner policy says company invoices will not have VAT. Do not infer or apply VAT, recalculate historical tax fields, or treat this policy alone as proof of statutory exemption. Qualified Romanian specialist validation is required where applicable.
- Vendor invoices need one canonical payable total. Meanings of other stored fields must be explicitly approved; discrepancies remain visible and are not automatically repaired.
- Discounts retain original and discounted values and explicitly record the discount. Financial corrections use linked credit notes/corrections and preserve the original invoice.
- Tax obligations remain separate from payment settlement; due and paid dates are distinct. The current Tax model does not establish a statutory tax engine.
- Direct costs are assigned to identifiable products/resources. Overhead uses approved percentages totaling exactly 100%. Allocation residuals remain visible and flagged.
- Product revenue, invoice totals and product costs must not be summed across currencies or treated as equivalent fields until their semantics are mapped.

## 5. Dates and reporting periods

| Field | Observed use | Contract guardrail |
| --- | --- | --- |
| transactions.timestamp | Finance dashboard month grouping. | Define event meaning/timezone; do not replace with invoice month. |
| invoice month and taxes.month | Billing/tax period and dashboard status counts. | Not a substitute for issue, due or payment date; validate legacy string values. |
| product_costs.month and product_revenues.month | Allocation period. | Define whether service, accrual or operational period before reporting. |
| financial_account_balances.reported_on | Snapshot as-of date. | Not a transaction posting date. |
| taxes.due_on and paid_on | Obligation due date and recorded paid date. | Keep distinct; do not infer missing dates or settlement. |

Operational reports label cash versus accrual views, name the date field/cutoff and use Europe/Bucharest for business boundaries. Unknown/discrepant values must not be presented as reconciled totals. Finalized-period corrections preserve originals and use linked corrections in the current open period unless a separately approved, auditable exception is defined. This contract does not establish statutory financial statements.

## 6. Writers, readers and required compatibility actions

| Consumer | Current behavior | Required action before implementation |
| --- | --- | --- |
| Filament transaction form/resource | Requires account, customer invoice and tax; vendor invoice optional; four legacy types; amount, method and timestamp required. | Reconcile reference requirements per workflow; add server-side validation/authorization only after mapping is approved. |
| Customer invoice form/resource | Requires customer/contract, identifiers, integer amount fields, month and Draft/Sent/Paid/Overdue status. | Preserve old values; canonical currency/total only through approved staged design. Keep owner policy separate from specialist tax review. |
| Vendor invoice form/resource | Requires vendor/contract, amount, VAT, currency, month, status and document identifiers. | Define canonical payable total and transitions; preserve/flag inconsistent values. |
| Tax form/resource | Categories vat/labor and statuses pending/paid/overdue. | Map obligation types/states; keep due and paid dates distinct; no inferred statutory computation. |
| FinancialOverviewData and dashboard tests | Sums integer transaction amounts by stored type and linked account currency; groups invoice statuses by month. | Preserve outputs until a migration plan exists; qualify unknown types/currencies. This is not a ledger or settlement proof. |
| Product cost service, resource actions and product overview | Allocates integer costs with current formula; revenue links to customer invoice; product prices use effective dates and legacy source traceability. | Compare implementation with approved allocation policy; define amount/currency/residual behavior; preserve historic rows. |
| Reconciliation/integrity commands | Read-only checks for configured anomalies; invoice-linked transaction sums are informational. | Extend only after mapping approval. Checks must not mutate data; clean output is not proof of legal correctness or settlement. |
| Activity-payroll service and tests | RON activity lines, decimal calculations, snapshots, duplicate protection and corrections. | Keep separate from statutory payroll; specialist-approved rules remain a release blocker for statutory calculations. |
| Factories/tests | Factories may generate arbitrary currency/status/type values; tests cover selected dashboard and reconciliation behavior. | Introduce reviewed representative fixtures in a future implementation change; never use production data as a test fixture. |

## 7. Validation and error contract for future code

Before runtime/schema work, define and test:

- Valid new RON and explicit non-RON amounts; invalid currency; missing/unknown legacy currency; negative transaction amount; unsupported precision.
- Unknown transaction type/status and conflicting or orphaned account/invoice/tax references.
- Over-allocation, mismatched invoice balance, duplicate retry, linked refund/reversal, transfer legs and separately recorded fee.
- Invoice amount disagreement, ambiguous canonical total, unknown legacy units and prohibited inferred VAT/recalculation.
- Product allocation totals below/above 100%, visible residual and correction without mutation of historical cost.
- Europe/Bucharest period boundaries, unknown statuses, currency-separated totals and post-close corrections.
- Authorization failure, audit evidence, atomicity, retry/recovery and read-only diagnostics.

Errors identify the field/rule and actionable correction without exposing sensitive values. Unknown/ambiguous rows use stable internal references and are excluded or qualified where inclusion would mislead. Exact warning/block severity must follow the decision register and be finalized in the relevant implementation issue.

## 8. Deployment and migration boundary

This is a contract/documentation deliverable. It makes no runtime, schema or data changes.

Before #307 migration planning or #308 schema implementation:

1. Record review of field mapping and canonical meanings by the accountable owner.
2. Record qualified Romanian accounting/tax review for applicable fiscal semantics and separate payroll specialist approval for statutory payroll.
3. Inventory legacy distinct values, nulls, malformed dates, suspicious amounts and conflicting relationships through an authorized audit process. Use synthetic/sanitized data in tests.
4. Define a staged compatibility rollout and deterministic mapping for known values; unknowns remain quarantined.
5. Quantify rows and totals by period, currency and status; provide before/after reconciliation and tested recovery. No amount scaling, currency fill, status rewrite, historical recalculation or backfill is authorized by this contract.
6. Coordinate updates to writers, readers, dashboards, commands, exports, uploads and tests; preserve original values/source references.
7. Run repository validation and MySQL migration/regression CI for any implementation PR. Passing tests do not certify statutory compliance.

## 9. Decision and review log

| Topic | Disposition | Evidence / remaining action |
| --- | --- | --- |
| Operational money precision, default currency, no automatic FX | Owner-approved policy | Decision register #304, Decisions 1 and 11; field-level legacy mapping remains. |
| Direction, refunds/reversals, transfers and fees | Owner-approved target behavior | Decisions 2 and 8; current types and links need mapping. |
| Company invoices will not have VAT | Owner-stated business policy only | Decisions 3 and 9; qualified Romanian tax/invoicing validation remains. |
| Canonical vendor payable total and unknown legacy currency | Owner-approved target behavior | Decision 10; exact field selection and legacy evidence remain. |
| Full-balance payment, explicit settlement confirmation, statuses | Owner-approved target behavior | Decisions 4, 5, 29, 30 and 32; transition/reference mapping and authorization remain. |
| Product direct cost/overhead and residuals | Owner-approved target behavior | Decision 7; current recalculation requires safe future design. |
| Cash/accrual views, dates and corrections | Owner-approved operational policy | Decisions 6, 20, 21 and 35; report-specific date fields/cutoffs need implementation specs. |
| Statutory tax/payroll and external compliance | Not approved by this document | Qualified specialist review and independently checked examples required before release. |
| Schema changes, conversion, historical backfill | Not authorized | Requires #307 plan, approvals, reconciliation and recovery evidence. |

## 10. Next steps

1. Review the field mapping against source documents and confirm/correct owner-policy mappings.
2. Resolve canonical-field choices and legacy value maps without mutating data.
3. Use [#307 currency and precision migration plan](https://github.com/deputy-proxy/Management-OS/issues/307) and the [migration-plan document](financial-precision-migration-plan.md) to quantify legacy rows and specify backfills/reconciliation/recovery. Do not start #308 schema work until contract and migration plan are approved.
4. Keep the [finance/payroll capability matrix](finance-payroll-readiness.md) and [decision register](financial-rules-decision-register.md) aligned with any new decisions.


Payroll calculation runs and results use immutable, idempotent snapshots; see [finance/payroll readiness](finance-payroll-readiness.md). No monetary or statutory results are inferred.
