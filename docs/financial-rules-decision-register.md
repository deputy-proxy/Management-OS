# Financial Rules Decision Register

**Status:** Company-owner business decisions recorded; independent review and Romanian accounting/tax specialist validation remain pending where applicable.  
**Issue:** [#304](https://github.com/deputy-proxy/Management-OS/issues/304)  
**Scope:** Management-OS operational financial semantics. This register does not certify statutory accounting, tax, payroll, or general-ledger compliance.

## Approval record

The company owner approved the business decisions below. Add the owner's name/role and formal approval date if required by project governance. This record does not claim that a qualified Romanian accounting/tax specialist or independent reviewer has approved statutory treatment.

## Decision 1: Currency and monetary precision

**Status:** Approved by company owner.

- Default currency is RON; any other currency must be explicit.
- Monetary storage should use fixed-precision decimal values.
- Use half-up rounding at a defined calculation step; each workflow must specify that step.
- Do not automatically convert currencies. Report currencies separately.
- Do not infer missing currency, convert legacy amounts, or migrate monetary fields until current field meanings and a conversion plan have been audited.
- Validate statutory accounting/tax implications with a qualified Romanian specialist where applicable.

## Decision 2: Transaction direction and signs

**Status:** Approved by company owner.

- Represent direction explicitly as money-in or money-out; transaction amount is non-negative.
- Record refunds as separate transactions linked to the original.
- Use linked reversals for reversals/corrections, preserving the original event rather than silently overwriting it.
- Keep payment method as metadata separate from direction and transaction type.
- Define allowed types, relationships, and reporting/balance effects before implementation. Specialist validation where applicable.

## Decision 3: Invoice totals and tax treatment

**Status:** Company-owner business policy recorded; statutory treatment requires qualified Romanian accounting/tax validation where applicable.

- The company owner states that company invoices will never have VAT.
- Do not infer or apply a VAT rate, and do not silently recalculate historical tax values.
- Preserve existing stored invoice values and flag discrepancies; do not silently correct them.
- Define canonical field units and the applicable invoice-total calculation before implementation.
- The owner's no-VAT statement is a business-policy assumption, not by itself proof of a statutory exemption or compliance. Confirm the actual Romanian invoicing/tax treatment with a qualified specialist where applicable.

## Decision 4: Payment allocation and settlement

**Status:** Approved by company owner; accounting validation pending where applicable.

- Approved invoice workflow permits one payment covering the full outstanding balance; multiple partial payments are not an approved workflow.
- Reject allocations exceeding the outstanding balance.
- Allocation must follow an explicit, deterministic, reviewable rule with an audit trail. Do not infer settlement from a transaction-invoice link alone.
- An authorized user explicitly confirms settlement. A zero calculated balance alone does not mark an invoice paid.
- Define allocation precedence, idempotency, rounding interactions, authorization, and refund/reversal effects before implementation.

## Decision 5: Invoice, transaction, and tax statuses

**Status:** Approved by company owner; statutory/accounting validation pending where applicable.

- Use one invoice status field including paid/unpaid, with paid status requiring explicit authorized confirmation.
- Track explicit transaction lifecycle statuses such as recorded, confirmed, reversed, and refunded as applicable; link refunds/reversals to the original.
- Keep the tax workflow separate from settlement; due and paid dates remain distinct.
- Create a mapping table for legacy free-text statuses, report unknown values, and preserve originals. Do not silently rewrite historical statuses.
- Define allowed statuses, transitions, permissions, and audit events before implementation.

## Decision 6: Reporting periods and accounting basis

**Status:** Approved by company owner; Romanian accounting validation pending where applicable.

- Provide cash and accrual views as separate, clearly labeled operational reports. Do not merge them into one ambiguous total.
- Preserve relevant revenue dates separately; each report explicitly selects the date it uses.
- Preserve expense invoice, receipt, due, and payment dates separately; each report explicitly selects the date it uses.
- Use Europe/Bucharest for business reporting boundaries.
- Define each report's date field and period cutoff before implementation. These operational views do not claim statutory accounting compliance.

## Decision 7: Product direct costs and overhead allocation

**Status:** Approved by company owner; accounting validation pending where applicable.

- Assign direct costs to a product/resource whenever the cost is identifiable.
- Allocate overhead using explicit, approved percentages.
- Require allocation percentages to total exactly 100% before allocation.
- Keep unallocated amounts and rounding residuals visible and flagged. Do not silently redistribute a remainder.
- Preserve historical costs. Existing implementation behavior is not itself policy approval; future implementation must follow these rules and include validation and tests.

## Decision 8: Transfers and transaction fees

**Status:** Approved by company owner; accounting validation pending where applicable.

- **Transfers between company accounts:** Use a transfer record plus linked account entries.
- **Bank/payment-provider fees:** Record fees explicitly and separately from principal, with traceability and controls to prevent double-counting.
- **Currencies and differences:** Support same-currency and explicitly documented cross-currency transfers. Do not perform automatic FX conversion. For cross-currency transfers, record source and destination amounts and the applied exchange rate explicitly.
- **Guardrails:** Define how transfers and fees affect balances and operational reports; preserve links between the transfer record and account entries. Validate accounting/tax treatment with a qualified specialist where applicable. No schema migration or historical-data backfill is authorized.

## Decision 9: Discounts and credit notes

**Status:** Approved by company owner; statutory/accounting validation pending where applicable.

- **Discounts:** Preserve original and discounted values, explicitly record the discount, and keep changes auditable.
- **Credit notes and corrections:** Use linked credit notes for financial corrections and preserve the original invoice. Permit non-financial metadata edits under an audit trail.
- **VAT/tax handling:** The owner states that company invoices will never have VAT. Do not infer VAT, apply a VAT rate, or silently recalculate historical tax values. The general principle of explicit approved values and no inference is retained, but VAT-bearing invoice assumptions are not applicable to the owner's stated business policy.
- **Guardrails:** Treat the no-VAT statement as company-owner business policy, not a compliance conclusion. Confirm Romanian tax/invoicing treatment with a qualified specialist where applicable. Preserve historical invoice values. No schema migration or historical-data backfill is authorized.

## Register summary

| Area | Approved rule | Required guardrail |
|---|---|---|
| Currency and precision | RON default; explicit other currencies; fixed-precision decimals; half-up rounding at defined workflow steps; no automatic FX | Audit field meanings before conversion/migration; report currencies separately |
| Transactions | Explicit money-in/money-out; non-negative amount; linked refunds/reversals; payment method is metadata | Preserve original events; define types, links, and report/balance effects |
| Invoices and tax treatment | Owner states invoices will never have VAT; no inferred VAT/rate or silent historical recalculation; preserve and flag legacy discrepancies | Define invoice field units/calculation; validate actual Romanian tax treatment with a qualified specialist; owner policy is not proof of statutory exemption |
| Payment allocation | One full-balance payment; reject over-allocation; deterministic auditable allocation; explicit authorized paid confirmation | Define precedence, idempotency, permissions, rounding, and reversal/refund effects |
| Statuses | Explicit invoice, transaction, and tax status semantics; preserve legacy status values | Map unknown values for review; define transitions, permissions, audit events |
| Reporting | Separate cash/accrual operational views; preserve dates; per-report date selection; Europe/Bucharest timezone | Explicit report date field and cutoff; Romanian accounting validation |
| Product costs | Identifiable direct costs assigned directly; overhead via approved percentages totaling exactly 100%; remainders visible/flagged | Preserve historical costs; test percentage totals and residual handling |
| Transfers and fees | Transfer record plus linked account entries; explicit fee records without double-counting; documented cross-currency amounts/rate; no automatic FX | Trace transfer/account entries and fee links; define balance/report effects |
| Discounts and credit notes | Preserve original and discounted values; explicitly record discounts; linked credit notes for financial corrections; audit non-financial metadata edits; no VAT inference | Preserve historical invoice values; validate tax treatment; no schema migration or historical backfill |
| Vendor invoices and legacy currency | Canonical payable total; validate other fields; new invoices default RON; legacy currency unknown until verified; preserve/flag inconsistent totals | Document field meanings and validation; no silent recalculation, migration, or backfill |
| Monetary precision and rounding | Two-decimal monetary values; higher-precision invoice calculation then round final total; allocation remainders visible and flagged | Clarify/test intermediate vs persisted precision; no silent redistribution or migration/backfill |
| Payroll | Recalculate from approved inputs; authorized linked corrections for finalized results; provisional estimates only until qualified statutory validation | Preserve audit trail; statutory payroll release blocked pending qualified review |
| Reconciliation discrepancies | Severity-based warnings/blocks; designated approvers with reasons/audit trail; unresolved items and qualified totals visible | Define severity/block criteria, permissions, evidence, and report qualification |
| Deletion and archival | Owner approves authorized deletion of used records and permanent deletion of unused mistaken records; archived records excluded from operational views but retained for audit/history | Destructive deletion of used financial/payroll records blocked pending qualified Romanian accounting/legal retention review |
| Permissions and approvals | Operation-specific role permissions; designated approver for consequential actions; self-approval allowed where role permits; no monetary thresholds defined | Document role/action matrix and audit approvals; validate applicable statutory/external controls |
| Reporting scope | Operational dashboards only; preserve and flag unknowns; disclose reconciliation checks, unresolved discrepancies, and limitations; no accounting reconciliation reports in this requirement | Do not conflate calculation with reconciliation; not statutory financial statements |
| Financial exports | Explicit per-data/operation export permission; preserve source values with unknown/discrepancy indicators; audit user, timestamp, scope, format, and outcome | Apply privacy/retention controls; no estimating or silent normalization |
| Financial notifications | Configurable overdue/failed-payment/material-discrepancy/approval events; recorded due dates only; Europe/Bucharest reporting timezone; log/retry/expose delivery failures | Define event criteria, retry policy, and operator visibility; never infer missing due dates |
| Historical data | No automatic inference or silent rewriting | No schema migration or historical-data backfill under these decisions |

## Decision 10: Vendor invoice fields, missing currency, and inconsistent totals

**Status:** Approved by company owner.

- **Vendor invoice fields:** Define one canonical payable total and validate other amount fields against it. Preserve discrepancies for review rather than silently resolving them.
- **Missing legacy currency:** Default new invoices to RON, but leave legacy currency unknown until verified from source records.
- **Inconsistent stored totals:** Preserve stored totals and flag discrepancies for review. Do not automatically recalculate or overwrite historical values.
- **Guardrails:** Document canonical field meanings and validation rules before implementation. No schema migration or historical-data backfill is authorized by this decision. Any later conversion or correction requires an audited mapping and approved plan.

## Decision 11: Monetary precision, invoice rounding, and allocation remainders

**Status:** Approved by company owner.

- **Monetary precision:** Store and calculate monetary values to two decimal places.
- **Invoice rounding:** Calculate using higher precision, then round the final invoice total.
- **Allocation remainders:** Keep any remainder visible and flagged for review; do not distribute it automatically.
- **Implementation clarification:** The technical contract must distinguish intermediate calculation precision from persisted monetary values, since the approved choices specify higher-precision invoice-total calculation alongside two-decimal monetary storage/calculation. Document and test this distinction before implementation.
- **Guardrails:** No silent changes to existing values, schema migration, or historical-data backfill are authorized by this decision. Qualified Romanian accounting/tax review remains pending where applicable.

## Decision 12: Payroll calculation, corrections, and statutory release

**Status:** Approved by company owner.

- **Payroll calculation source:** Recalculate from approved inputs, but require review and explicit authorization before replacing previously finalized payroll results.
- **Finalized payroll corrections:** Preserve the original and create linked corrections, with an audit trail and authorization for every correction to finalized payroll.
- **Statutory rules:** Company-defined rules may be used for provisional internal estimates, but statutory payroll release remains blocked until qualified Romanian payroll/accounting specialist validation is complete.
- **Guardrails:** Do not silently overwrite finalized payroll. Clearly distinguish provisional estimates from statutory payroll outputs. This is company-owner policy, not independent specialist validation; the statutory-release blocker remains until qualified review is obtained.

## Decision 13: Financial reconciliation and discrepancies

**Status:** Approved by company owner.

- **Discrepancy response:** Classify discrepancies by severity. Use warnings for review and block material or safety-critical issues.
- **Resolution authority:** Designated approvers may resolve discrepancies, with a recorded reason and audit trail. Require specialist approval where statutory treatment is involved.
- **Reporting:** Show unresolved discrepancies explicitly and clearly qualify affected totals.
- **Implementation guardrails:** Define severity categories, blocking criteria, approver permissions, required resolution evidence, and how qualified totals are displayed before implementation. Do not silently suppress discrepancies or label affected totals as fully reconciled.

## Decision 14: Financial record deletion and archival

**Status:** Approved by company owner, with statutory-retention implementation blocker.

- **Records already used in a financial event:** The company owner approves allowing deletion by authorized users, including records used in transactions, invoices, payments, or finalized payroll.
- **Unused mistaken records:** The company owner approves permanent deletion of records created by mistake before they have been used.
- **Archived records in reports:** Exclude archived records from normal operational views while retaining them for audit and historical reporting.
- **Statutory-retention blocker:** Deletion of financial or payroll records may conflict with statutory retention, audit-trail, accounting, tax, or employment requirements. Do not implement destructive deletion of used financial records or finalized payroll until a qualified Romanian accounting/legal specialist confirms the permitted scope, retention requirements, and any required immutable audit evidence. Until then, preserve existing records; this owner decision alone does not authorize destructive migration or bulk deletion.

## Decision 15: Financial permissions and approval limits

**Status:** Approved by company owner.

- **Ordinary financial record access:** Users need explicit permissions for the relevant financial operation, limited by role.
- **Consequential financial actions:** Payments, write-offs, and other consequential actions require a designated approver; this decision establishes no monetary thresholds.
- **Self-approval:** A user may approve their own action when their role permits approval.
- **Implementation guardrails:** Document permissions by operation and role, define which actions require designated approval, and audit approval identity and outcome. Do not invent monetary thresholds. Self-approval is explicitly allowed by this owner decision; statutory or external control requirements still need specialist validation where applicable.

## Decision 16: Financial reporting and reconciliation scope

**Status:** Approved by company owner.

- **Reconciliation status:** A report may be marked reconciled only when it passes defined reconciliation checks and discloses unresolved discrepancies and limitations.
- **Missing or ambiguous data:** Preserve unknowns, flag them, and qualify affected totals. Do not fill gaps using an assumed interpretation.
- **Default reporting scope:** Operational dashboards are the required default scope; accounting reconciliation reporting is out of scope for this requirement.
- **Implementation guardrails:** Do not label successful calculation as reconciliation. Document the checks and report limitations used by operational dashboards. Do not expand this issue's scope into accounting reconciliation reports without a separate approved requirement. These operational dashboards are not statutory financial statements.

## Decision 17: Financial data exports

**Status:** Approved by company owner.

- **Export access:** Users need explicit export permission for the relevant data and operation.
- **Unknown or flagged values:** Preserve source values and include clear indicators for unknowns or flagged discrepancies.
- **Export audit trail:** Record the user, timestamp, export scope and format, and outcome, subject to applicable privacy and retention controls.
- **Implementation guardrails:** Do not estimate or silently normalize values in exports. Enforce export permission at the relevant operation and include enough metadata to audit what was exported and whether the export succeeded, while following applicable privacy and retention controls.

## Decision 18: Financial notifications and overdue items

**Status:** Approved by company owner.

- **Notification events:** Support configured events such as overdue invoices, failed payments, unresolved material discrepancies, and required approvals.
- **Due dates and overdue status:** Use recorded due dates, flag missing dates, and apply the approved `Europe/Bucharest` business reporting timezone.
- **Delivery failures:** Log failures, retry under a defined policy, and expose unresolved delivery failures to authorized operators.
- **Implementation guardrails:** Do not infer missing due dates. Define configurable event criteria, retry limits/backoff, and operator visibility before implementation. Keep delivery failure status distinct from successful delivery.

## Cross-cutting implementation controls

1. **No schema migration or historical-data backfill is authorized by these decisions.** Any later proposal requires audited field mappings, before/after totals, reconciliation tests, and a recovery plan, reviewed and approved before execution.
2. Preserve legacy values and financial events; do not silently rewrite, normalize, or redistribute them.
3. Obtain independent review and qualified Romanian accounting/tax specialist validation for applicable statutory treatment, including invoice tax treatment and payroll.
4. Convert approved policy into canonical data contracts, validation rules, tests, and dependent implementation issues before changing financial behavior.
5. Existing integer fields, rounding calls, free-text statuses, relationships, and dashboard groupings describe current code behavior; they do not prove approved accounting semantics.
