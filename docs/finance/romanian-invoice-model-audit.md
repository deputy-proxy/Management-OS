# Existing invoice model audit and compatibility map

**Status:** source-code audit; accounting/fiscal semantics and production data remain unverified
**Research date:** 25 October 2026
**Scope:** current customer/vendor invoice tables, model fields, admin workflows, relationships, consumers, compatibility risks and migration prerequisites. No schema changes are proposed by this audit.

Related documents:
- [Romanian invoicing requirements baseline](romanian-invoicing-requirements.md)
- [RO e-Factura technical integration requirements](romanian-efactura-technical-requirements.md)
- [Finance/payroll capability matrix](../finance-payroll-readiness.md)
- [Financial data contract](../financial-data-contract.md)
- [Financial normalization decision register](../financial-rules-decision-register.md)

## 1. Executive findings

The repository has two separate legacy aggregates, `customer_invoices` and `vendor_invoices`. They are used by Filament CRUD screens, customer/calendar/finance summaries, transaction references, payment-allocation proposals/confirmations, product revenue, activity logging, and financial normalization/reconciliation.

They are **not** a canonical issued-invoice model and do not establish the legal or operational state of a Romanian invoice. In particular:

- The two tables do not share one consistent field contract.
- Several legacy amounts are integer columns; nullable decimal canonical fields have been added but are not a lossless, approved migration by themselves.
- Customer invoices have no currency field in the original schema; both tables now have a nullable `currency_code`.
- VAT and total semantics are not consistently defined. The customer schema has no separate gross-total column; vendor `amount` is present but its relationship to net and VAT is not approved.
- Customer `month` is a string; vendor `month` was later changed to a date. Neither is a verified supply/tax-point date or issue date.
- `status` is free text in storage. Customer UI offers Draft/Sent/Paid/Overdue; vendor UI accepts free text. These values do not define a legally complete invoice lifecycle.
- The `invoice`, `identifier`, and `invoice_number` fields are separate strings with unclear common semantics. They are not proven to be a file artifact, legal invoice number, globally unique identifier, e-Factura message ID, or XML payload.
- There are no invoice line/tax-breakdown tables or e-Factura submission/response records in the inspected invoice model.
- Soft deletion and editable CRUD are available. Issued invoice immutability, correction links, numbering control and retention policy are not implemented by these models.

Do not infer missing meanings, backfill canonical fields automatically, or change schemas until Finance/accounting approves the mapping and the production data is reconciled.

## 2. Customer invoice field-by-field mapping

Sources: `database/migrations/2026_04_12_110643_create_customer_invoices_table.php`, `2026_04_20_003941_add_column_identifier_to_customer_invoices.php`, `2026_10_24_000000_add_canonical_financial_amount_columns.php`, `2026_10_24_000001_add_financial_normalization_audit.php`, `app/Models/CustomerInvoice.php`, and its Filament resource/schema/table.

| Stored field | Current storage / observed use | Canonical invoice mapping and risk |
| --- | --- | --- |
| `id` | Auto-increment primary key. Used by Eloquent relationships and downstream references. | Keep as the stable internal legacy ID and retain a mapping to any future canonical invoice. It is not a legal invoice number. |
| `customer_id` | Required `foreignId` column in the initial migration; model `belongsTo(Customer)`; selected by customer name in the form. | Party relationship only. The current master row is not a point-in-time seller/buyer snapshot; historical identity/address/tax-ID values must be designed separately. DB constraint/legacy orphan status must be verified before migration. |
| `customer_contract_id` | Required `foreignId`; model `belongsTo(CustomerContract)`; form selects contract code. | Commercial source reference. It does not prove the tax point, invoice recipient, supply details or invoice terms. Preserve link and reconcile historical rows. |
| `amount_without_vat` | Required integer in initial migration; editable numeric input labelled “Amount (excluding VAT)”; used by legacy consumers/factories and some configured normalization rules. | Unit, minor-unit convention, rounding and authoritative status are unverified. Do not convert integer to decimal by assuming major units or cents. Preserve original value during any migration. |
| `vat` | Required integer; editable numeric input labelled “VAT Amount”. | UI label suggests an amount, but historical unit, rate-versus-amount semantics, category, rounding and effective date are not proven. Do not derive tax from this field alone. |
| `amount_without_vat_decimal` | Nullable `DECIMAL(19,2)` added 24 October 2026; model cast `decimal:2`; fillable and activity-logged. | Canonical-format candidate, not an approved source of truth. Nullability permits legacy rows without a value; population requires a reviewed mapping and reconciliation. |
| `vat_decimal` | Nullable `DECIMAL(19,2)`; model cast `decimal:2`; fillable and activity-logged. | Canonical-format candidate for a VAT amount only after Finance approves semantics, tax category/rate and rounding. Not a VAT rate field. |
| `currency_code` | Nullable three-character column added in the canonical financial amount migration; model fillable and activity-logged. | ISO-style currency code format is implied by width, but no required default or historical currency is established. Do not silently default old invoices to RON. |
| `month` | Required string; used by customer dashboard, finance overview, calendar events and date-range filtering. Factory/tests commonly use ISO date strings such as `2026-10-01`. | Billing-period marker, not a verified issue date, supply/tax point, due date or accounting period policy. Invalid/non-ISO legacy values may not sort/filter as expected. |
| `status` | Required string; form options are Draft, Sent, Paid, Overdue. Displayed by tables, dashboard, calendar and finance summary. | Presentation/workflow label only. It cannot be mapped one-to-one to issue, transmission, acceptance, correction, cancellation or payment state without an approved transition map. |
| `status_code` | Nullable code added with financial normalization audit; fillable and activity-logged. | Normalization/status vocabulary must be reviewed before mapping. It is not proof that the free-text status was migrated or that invoice lifecycle transitions are enforced. |
| `invoice` | Required string; form exposes a required text input; shown in table/infolist. | Meaning is unclear. It may be a reference/path/name, but the inspected form does not establish a file-upload contract. Do not treat it as canonical XML, rendered PDF or immutable document storage without evidence. |
| `identifier` | Required string added in a later migration; form/table/infolist expose it as a text field. | Semantics and uniqueness scope are unspecified. Do not assume it is the legal invoice number, internal ID, UUID or ANAF submission ID. |
| `created_at`, `updated_at` | Laravel timestamps. | Technical record timestamps only, not issue/submission/acceptance timestamps. |
| `deleted_at` | Soft deletes enabled; resource includes delete, force-delete and restore actions and queries include trashed records. | Does not satisfy legal retention or issued-document immutability. Existing delete/force-delete behavior requires an approved policy before issued invoices are introduced. |

## 3. Vendor invoice field-by-field mapping

Sources: `database/migrations/2026_04_08_203149_create_vendor_invoices_table.php`, `2026_04_08_203646_add_column_invoice_to_vendor_invoices.php`, `2026_04_19_232628_add_column_amount_to_vendor_invoices.php`, `2026_04_19_233214_add_columns_invoice_number_identifier_to_vendor_invoices.php`, `2026_04_19_233300_update_column_month_on_vendor_invoices.php`, the canonical financial amount/audit migrations, `app/Models/VendorInvoice.php`, and its Filament resource/schema/table.

| Stored field | Current storage / observed use | Canonical invoice mapping and risk |
| --- | --- | --- |
| `id` | Auto-increment primary key. Used by Eloquent and downstream links. | Stable internal legacy key; not the supplier's legal invoice number. Preserve cross-reference in future migration. |
| `vendor_id` | Required `foreignId`; model `belongsTo(Vendor)`; form selects vendor name. | Supplier relationship only. Vendor master currently lacks a proven point-in-time supplier tax/address snapshot for the received invoice. |
| `vendor_contract_id` | Required `foreignId`; model `belongsTo(VendorContract)`; form selects contract code. | Commercial reference; not proof of the invoice contents, tax treatment or payment terms. Preserve and reconcile. |
| `amount_without_vat` | Required integer; present in legacy form? The current form primarily exposes `amount` and `vat`; this field is included in the model and activity log. | Historical unit and semantics unverified. Do not calculate or overwrite from `amount` and `vat` without approved mapping. |
| `vat` | Required integer; editable numeric input labelled VAT. | VAT amount/rate semantics, category, effective date and rounding are unverified. |
| `amount` | Integer added by a later migration; editable numeric input in the form; factory also populates it. | It is not proven whether this is gross, net, payable, legacy minor units or another amount. Do not assume `amount = amount_without_vat + vat`. |
| `amount_decimal` | Nullable `DECIMAL(19,2)` added 24 October 2026; cast `decimal:2`; fillable and activity-logged. | Candidate canonical amount, but the business meaning of legacy `amount` is unresolved. Keep null until a row-level mapping is approved. |
| `amount_without_vat_decimal` | Nullable `DECIMAL(19,2)`; cast `decimal:2`; fillable and activity-logged. | Candidate net amount; no automatic conversion from integer field without approved unit semantics. |
| `vat_decimal` | Nullable `DECIMAL(19,2)`; cast `decimal:2`; fillable and activity-logged. | Candidate VAT amount; tax category/rate and historical precision still need approval. |
| `currency` | Required string in initial migration; editable text input. | Legacy currency field with no observed normalization guarantee; allowed values and historical validity need audit. |
| `currency_code` | Nullable three-character column added in the canonical amount migration; model fillable and activity-logged. | Canonical-format candidate. Existing `currency` and new `currency_code` can disagree; do not choose one silently. |
| `month` | Initially string, later changed to `DATE`; vendor form uses DatePicker. Finance overview filters this date. | Billing-period/date marker, not proven issue date, supply/tax point or due date. Migration altered the column type, so production legacy conversion and rejected/null/invalid values must be checked. |
| `status` | Required string; form uses free-text input; displayed in list/infolist and finance summary. | No enum/transition policy. Do not infer issue, received, approved, paid, rejected or overdue solely from text. |
| `status_code` | Nullable code added with financial normalization audit; fillable and activity-logged. | Needs an approved mapping and migration plan; does not enforce state transitions by itself. |
| `invoice` | Required string added after table creation; current form exposes a required text input. | Likely document reference, but no file-upload or content-hash contract is established by the inspected resource. Do not assume original supplier XML/PDF is retained. |
| `invoice_number` | Required string added in a later migration; editable text input and included in activity log. | Likely intended as a supplier invoice number, but uniqueness, issuer scope, series semantics and relation to `identifier` are not enforced by the inspected migration. |
| `identifier` | Required string added alongside `invoice_number`; editable text input. | Meaning and uniqueness scope are not defined. Keep distinct from supplier invoice number and future internal/ANAF identifiers. |
| `created_at`, `updated_at` | Laravel timestamps. | Technical record timestamps only. |
| `deleted_at` | Soft deletes enabled; resource exposes delete, force-delete and restore actions. | Retention and historical traceability policy must be approved; soft deletes are not a legal records schedule. |

## 4. Relationships and downstream consumers

| Consumer / workflow | Verified repository behavior | Compatibility and migration implication |
| --- | --- | --- |
| Customer invoice Filament resource | CRUD create/edit/view/list; form edits identifiers, customer/contract, net/VAT integers, month and a fixed status list. Permission gates exist for index/create/update/delete. Soft-deleted records can be viewed/restored/force-deleted. | Resource can edit current rows without an issued/draft lock. New canonical invoice workflows must not silently reinterpret current editable records as issued documents. |
| Vendor invoice Filament resource | CRUD create/edit/view/list; form edits identifier, invoice number, invoice string, vendor/contract, amount, VAT, currency, month and free-text status. Permission gates exist; soft-delete actions are available. | Supplier document and canonical XML evidence need separate artifact semantics; free text cannot serve as a lifecycle state machine. |
| `Transaction` / `transactions` | Model belongs to customer invoice, vendor invoice and tax; the legacy schema has `customer_invoice_id`, `vendor_invoice_id` and `tax_id`. Read-only reconciliation flags transactions linked to both invoice directions. | Preserve existing links and resolve historical rows with both links set before adding stronger constraints. Transaction amounts/currencies are separate from invoice totals; do not infer settlement from a link. |
| `PaymentAllocation` | Supports proposed/confirmed/reversed/rejected allocation states; invoice allocation requires exactly one customer or vendor target, fee allocations require none. Uses decimal amount/currency, audit actor/timestamps/reasons and restrict-on-delete relationships. | This is a separate allocation workflow. A confirmed allocation does not itself prove an invoice is paid, and invoice status must not be rewritten from it without an approved settlement contract. Preserve allocation history and foreign-key behavior. |
| `ProductRevenue` | Links a product and customer invoice; stores month, integer amount and nullable canonical decimal/currency. CustomerInvoice exposes a `hasOne(productRevenue)` relationship. | Revenue allocation amount and invoice amount may have different semantics. Keep separate until the owner approves the relation, uniqueness, currency and reconciliation rules. |
| Customer dashboard | Counts non-deleted customer invoices grouped by `status` within the current month based on `month`. | Affects status counts only; does not calculate receivables or validate invoice status. Preserve date filtering semantics or deliberately migrate with tests. |
| Finance overview | Groups customer invoices by `status` and vendor invoices by `status,currency` for the current and previous period based on `month`; does not sum invoice amounts. | Date type/string differences and status vocabulary affect grouping/filtering. Do not start summing legacy amounts without an approved currency and total contract. |
| Calendar | Exposes customer invoices as all-day events based on `month`, labelled with customer and status, guarded by `customer_invoice.index`. | Calendar event date is not an issue/due date. If canonical issue/due dates are added, keep billing-period events distinct unless product requirements explicitly change. |
| Financial normalization service and configuration | Maps selected legacy amounts/currency/status fields to nullable canonical fields under named, approved normalization rules. Invoice rules include customer VAT zero and vendor total-equals-components; audit reports unresolved semantics instead of guessing. | Preserve the approved rule registry and audit trail. Do not broaden a rule or backfill a field just because a decimal column exists; the row must meet the exact approved mapping. |
| Finance reconciliation audit | Checks configured invoice-total formulas only when a rule is explicitly approved; reports unresolved amount/currency/classification gaps. | Future invoice-model changes need an explicit rule version, independent fixtures and migration reconciliation. A clean audit is not proof of legal compliance. |
| Activity log | Models log selected monetary, currency, status, identifier and relation fields with dirty-only logging. | Audit log is useful history but is not a full immutable invoice snapshot or retention system. Avoid putting XML, credentials or unnecessary personal data into activity properties. |
| Factories/seeders/tests | Customer/vendor factories and seeders create legacy-style invoices; tests cover canonical columns, dashboard/calendar status counts, allocation behavior and reconciliation diagnostics. | Update factories and regression tests alongside any future contract change. Synthetic test rows do not establish production data quality. |

## 5. Existing data and migration risks

1. **Unknown amount units.** The original integer amount columns do not encode whether values represent major units, minor units, rounded totals, or imported values. Do not multiply/divide by 100 or populate decimals until verified from source documents and production samples.
2. **VAT semantics are ambiguous.** The customer form calls `vat` a VAT amount, but the model/schema alone cannot prove that historical rows obey this. Vendor `amount`, `amount_without_vat` and `vat` do not have an approved formula.
3. **Currency disagreement or absence.** Customer invoices originally lack currency. Vendor invoices have legacy `currency` plus nullable `currency_code`. Do not infer RON or overwrite one from the other without a row-level decision.
4. **Dates have different types and semantics.** Customer month remains a string; vendor month changed to date. Neither replaces issue date, supply date, due date or transmission deadline. Invalid historical values may break range queries.
5. **Identifier collisions.** `invoice`, `identifier` and `invoice_number` have no established shared uniqueness rule. Preserve all source values and add a separate mapping rather than renaming/reusing a field by assumption.
6. **Relationships and orphans.** The initial invoice foreign IDs do not declare explicit database foreign-key constraints in the shown migrations. Existing transaction and product-revenue links are important to finance history. Run a read-only production audit before adding constraints or changing target relationships.
7. **Dual invoice links on transactions.** Legacy transactions can have both customer and vendor invoice references; existing audit tests intentionally report this. Do not automatically clear either side or force a new check constraint until every occurrence is reconciled.
8. **Soft deletion and destructive actions.** CRUD screens expose force deletion. New issued invoices and submission artifacts need retention/immutability rules and restrictive deletion behavior; a migration must not silently delete legacy data.
9. **Status normalization.** Customer status choices and vendor free text differ. Do not map old statuses to issued/accepted/paid without owner-approved transition semantics and a report of unmatched values.
10. **Existing audit trails.** Financial normalization and activity logs contain historical evidence. Migrations must preserve these records, source values, allocation history, document references and old invoice IDs.
11. **Canonical columns are nullable by design.** Their presence does not imply they are populated, correct or approved. Keep legacy and canonical values side-by-side during a staged migration and produce a before/after reconciliation report.
12. **Production data is not available in this repository audit.** Source code and synthetic tests do not establish actual null rates, invalid currencies, duplicate identifiers, orphan references, amount distributions or document availability. Production preflight is a release gate.

## 6. Recommended migration sequence

This is a gated plan, not authorization to execute migrations:

1. **Inventory only:** export counts, null distributions, distinct status/currency values, date-format anomalies, duplicate identifiers and orphan/dual-link reports from a read-only production snapshot. Do not export taxpayer data to unapproved locations.
2. **Reconcile source evidence:** sample legacy rows against actual invoice/source documents with authorized Finance staff. Record field-level units and semantics with provenance.
3. **Approve canonical contract:** agree separate customer-issued, vendor-received and payment-allocation concepts, canonical field types/precision, status transitions, issue snapshots, line/tax details and correction/retention requirements.
4. **Define explicit mapping:** map each legacy field to a canonical field or mark it legacy-only/ambiguous. Each automatic conversion must have a documented rule, effective scope, validation and exception behavior.
5. **Add staged structures:** preserve old columns/IDs/links and document artifacts. Add new fields/tables as approved, initially nullable where legacy rows cannot be safely mapped. Do not destructively rename/drop old columns in the first rollout.
6. **Backfill only verified rows:** use deterministic, idempotent commands with dry-run mode, per-row outcome/reason, immutable source values, audit history and a bounded batch size. Quarantine ambiguous rows for manual review.
7. **Reconcile before cutover:** compare source counts, amounts by currency and approved formula, relations, deleted rows, allocations, product revenue, status counts and artifact hashes. Investigate every difference; do not use a tolerance to hide unexplained variance.
8. **Dual-read or shadow verification:** compare canonical outputs with the legacy screens/reports using synthetic and approved production snapshots. Do not enable issuance/transmission merely because a migration succeeded.
9. **Cut over with rollback plan:** define a release window, backup/restore evidence, migration reversal constraints and operator decision authority. Preserve both mappings and the audit trail after cutover.
10. **Retire legacy fields later:** only after the owner signs the reconciliation and confirms no remaining consumers; use a separate issue/PR with explicit recovery and retention plan.

## 7. Owner decisions required before canonical model/schema work

- Which existing customer-invoice records represent issued documents versus draft billing records or internal placeholders?
- What are the exact units/semantics of each legacy integer amount field, with source evidence?
- Is `vat` always a monetary amount, and which effective-dated tax category/rate produced it?
- What is the approved relation between vendor `amount`, `amount_without_vat` and `vat`?
- What was the intended meaning and uniqueness scope of `invoice`, `identifier` and `invoice_number`?
- Which party snapshots and tax identifiers must be retained for issued and received documents?
- What are the approved issue, correction, cancellation, e-Factura and payment states, and who may perform each transition?
- Which transaction rows with both invoice links, missing links, or deleted targets need a reviewed business decision?
- What source files are actually stored for existing invoices, where are they stored, and what is their retention/backup status?
- Which production database engine/version and deployed migration state are in use? CI MySQL 8.0 does not establish the exact production minor version.
- Who approves field mapping, migration output, access policy, retention and cutover evidence?

## 8. Acceptance and review record

- Field-by-field customer/vendor mapping: **documented from repository source**.
- Existing UI, relationships and downstream consumers: **mapped from repository source**.
- Production data distributions, actual stored amount units and source-document availability: **not verified**.
- Canonical issued-invoice model and schema changes: **not approved by this audit**.
- Qualified Romanian accountant/tax adviser review: **must be recorded separately; not inferred from this code audit**.
- Finance owner / technical owner approval of the mapping and migration plan: **pending**.
- Schema changes: **none made**.
