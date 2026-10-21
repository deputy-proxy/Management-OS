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
| Identifiers and duplicates | Scoped identifier uniqueness; preserve external IDs; flag and preserve possible duplicates for review; idempotent retries with attempt audit trail | Define uniqueness and detection criteria; no automatic merge or duplicate financial effects |
| Financial periods | Date field defined per report/event; owner selected automatic rewrite of original-period figures after correction; authorized period close with audit history | Historical rewrite blocked pending clarification due to conflict with preservation/correction policy and specialist review |
| Finalized-period corrections | Preserve original and period history; linked correction in current open period; block unresolved treatment pending approval; Decision 21 supersedes automatic rewrite selection | No automatic rewrite of finalized-period figures; audit link to original required |
| Retention | Category-based periods subject to Romanian accounting/legal review; owner selected indefinite archiving; conflicting deletion requests blocked pending authorized review | Blanket indefinite retention blocked pending privacy and category-specific legal review |
| Outages and recovery | Atomic operations where possible; preserve failure evidence; idempotent retries; documented restore with integrity/totals/event reconciliation; authorized operator resumes after checks | Record recovery outcome and verify financial integrity before resuming |
| Manual uploads | Preview accepted/rejected rows, warnings, and changes; reject entire file on blocking errors; authorized confirmation and audit trail before import | Define validation, blocking/warning rules, preview, and permissions; no migration/backfill |
| Upload formats and source files | Explicitly approved formats/templates by upload purpose; retain original upload with metadata subject to approved retention/access policy; reject unexpected structures unless an approved versioned mapping exists | Define supported templates and mapping governance; validate retention, access, and privacy; no migration/backfill |
| Retained upload file access and retention | General financial-record access permits viewing/downloading; audit uploads only; owner selects indefinite retention for all upload types | Privacy, security, and category-specific Romanian legal/accounting retention review required; no migration/backfill |
| Upload security and file limits | Reject suspected malicious/failed-scan files without retaining them; block import if scan unavailable/inconclusive pending required checks or authorized review; warn on large files but allow | Define security checks and review flow; size/complexity thresholds remain unspecified; no migration/backfill |
| Upload duplicates and financial conflicts | Flag potential duplicates and preserve both pending review; block conflicting financial changes for authorized review; detect repeated uploads and prevent duplicate effects while recording attempts | Define duplicate/conflict criteria and idempotency identity; no migration/backfill |
| Payment allocation and balance mismatches | Deterministic configured allocation order with preview/authorized confirmation; flag payment/invoice mismatches and do not mark paid without authorized confirmation | Define allocation ordering, preview and mismatch handling; no migration/backfill |
| Transaction types and invoice states | Distinct or linked workflows for money-in/out, refunds, reversals, transfers, and fees; documented invoice transitions with authorized paid confirmation and audit | Define types, state machine, permissions, and audit events; no migration/backfill |
| Finalized-period correction and reopening | Preserve original; linked corrections in current open period; authorized role, reason, audit trail, and close/reopen history | No automatic finalized-period rewrite; unresolved corrections blocked; no migration/backfill |
| Discrepancy severity and unknown values | Document severity rules; block material/safety-critical operations; preserve/flag unknowns and qualify affected totals | Define criteria and report qualification; no inference or silent suppression |
| Import atomicity and confirmation | Reject whole file on blocking row errors; show warnings and require authorized confirmation; atomic processing where possible and safe retry/recovery | Record partial outcomes and verify integrity before resuming; no migration/backfill |
| Financial permissions and notifications | Role/operation permissions and designated approval for consequential actions; log/retry notification failures and expose unresolved failures | Define role matrix, approvers, retry policy and operator visibility; no migration/backfill |
| Upload audit evidence | Record upload identity, uploader, timestamp, preview/confirmation, outcome, accepted/rejected row references, and resulting changes | Define audit schema and access/retention controls; no migration/backfill |
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

## Decision 19: Financial identifiers and duplicate records

**Status:** Approved by company owner.

- **Identifier uniqueness:** Define uniqueness by record type and relevant business scope; preserve original external identifiers and detect conflicts.
- **Possible duplicates:** Flag possible duplicates for review and preserve both records until resolved.
- **Repeated imports and retries:** Use a defined idempotency key or source identity so retries do not create duplicate financial effects; preserve audit evidence of attempts.
- **Implementation guardrails:** Define uniqueness scopes and duplicate-detection criteria before implementation. Do not automatically merge possible duplicates or discard source identifiers. Idempotency must prevent duplicate financial effects without erasing the history of repeated attempts.

## Decision 20: Financial periods and month-end reporting

**Status:** Approved by company owner; correction behavior has an implementation blocker.

- **Reporting period date:** Define the date field by report and event type, document it, and preserve relevant dates separately.
- **Corrections after period finalization:** The company owner selected automatic rewriting of the original period's figures when a correction is made.
- **Period close:** Require an authorized user to finalize the period, retain close history, and audit any reopening.
- **Implementation blocker / clarification required:** Automatic rewriting of original-period figures conflicts with prior approved requirements to preserve historical financial records, retain linked corrections, and audit changes. Record the owner's current selection, but do not implement automatic historical rewrites until the intended behavior is clarified and applicable Romanian accounting/tax requirements are reviewed by a qualified specialist. Period finalization and reopening must remain auditable.

## Decision 21: Corrections after a finalized period

**Status:** Approved by company owner; supersedes the conflicting correction selection in Decision 20.

- **Correction period:** Preserve the original record and period history; record a linked correction in the current open period.
- **Unresolved correction treatment:** Preserve original data and block the affected correction until its treatment is approved.
- **Resolution of Decision 20 conflict:** Do not rewrite finalized-period figures automatically. Preserve historical records, use linked corrections in the current open period, and keep corrections blocked until treatment is approved where unresolved or specialist review is required. Maintain an auditable link between the correction and original record.

## Decision 22: Financial data retention

**Status:** Approved by company owner; blanket indefinite-retention behavior requires specialist review.

- **Retention periods:** Define retention by record category, subject to qualified Romanian accounting/legal review of applicable obligations.
- **Retention deadline:** The company owner selected archiving records indefinitely without further review.
- **Conflicting deletion request:** Block deletion until the conflict is reviewed and resolved by an authorized person.
- **Implementation guardrails / clarification needed:** Indefinite retention may conflict with data minimization, privacy, or category-specific legal obligations. Record the owner's choice, but do not implement indefinite retention as a blanket rule until qualified review establishes which categories may be retained indefinitely and which require deletion or anonymization. Deletion requests that conflict with retention requirements remain blocked pending authorized review.

## Decision 23: Financial system outages and recovery

**Status:** Approved by company owner.

- **Partial failure:** Use atomic transactions where possible, preserve failure evidence, and prevent retries from creating duplicate financial effects.
- **Data recovery:** Restore using a documented procedure, validate integrity and financial totals, reconcile affected events, and record the recovery outcome.
- **Resume processing:** An authorized operator may confirm technical recovery and required integrity checks have passed before financial processing resumes.
- **Implementation guardrails:** Document failure handling and recovery steps, verify financial integrity and affected events before resuming, and use idempotency controls for retries. The approved resumption authority is an authorized operator after required technical and integrity checks; do not add an extra finance-approver gate unless separately approved.

## Scope clarification: manual uploads only

**Status:** Confirmed by company owner.

There is no external financial integration. Financial data input is through manual uploads. Integration-specific questions in the proposed Decision 24 are not applicable and must not be treated as approved policy.

The next requirements decision should define the manual-upload workflow: validate the source file and required fields, preview proposed changes, detect duplicate/conflicting records, preserve unknown values and source data, and define what must be rejected versus flagged for authorized review before financial records are changed.

Existing guardrails remain in force: no schema migration or historical-data backfill is authorized by the decision register.

## Decision 24: Manual financial uploads

**Status:** Approved by company owner.

- **Upload preview:** Validate the file and show a preview of accepted rows, rejected rows, warnings, and proposed changes before importing.
- **Mixed valid/invalid rows:** Reject the entire file if any row has a blocking error; allow warnings only where explicitly permitted.
- **Import confirmation:** Apply uploaded data only after an authorized user confirms the preview, and record the upload and resulting changes in an audit trail.
- **Implementation guardrails:** Define file/row validation, blocking errors versus permitted warnings, preview contents, and authorization rules before implementation. Do not silently skip invalid rows or change financial records before authorized confirmation. No schema migration or historical-data backfill is authorized by this decision.

## Decision 25: Manual upload formats and source records

**Status:** Approved by company owner; retention, access, and privacy controls require appropriate review.

- **Supported formats:** Support only explicitly approved file formats and templates for each upload purpose. Reject unsupported formats.
- **Original source file:** Retain the original uploaded file together with upload metadata, subject to an approved retention and access policy.
- **Unexpected structure:** Reject unexpected file structures by default. Permit only explicitly approved, versioned mappings; do not guess or silently map columns.
- **Implementation guardrails:** Define supported formats/templates by upload purpose, versioning and approval of mappings, and access/retention controls before implementation. Apply the Decision 24 preview, blocking-error, and authorized-confirmation requirements. No schema migration or historical-data backfill is authorized.

## Decision 26: Access to and retention of original upload files

**Status:** Company-owner policy recorded; privacy, security, and applicable legal/accounting retention requirements remain subject to review.

- **Access:** Any user with general financial-record access may view or download original uploaded files.
- **Audit events:** Audit upload events. This decision does not require separate audit records for subsequent views or downloads.
- **Retention:** The company owner selected indefinite retention of original uploaded files for all upload types.
- **Implementation guardrails:** Do not treat owner approval as resolving applicable privacy, security, data-minimization, or Romanian legal/accounting obligations. Before implementing broad access or blanket indefinite retention, obtain appropriate review and define any required safeguards or exceptions. Preserve upload audit records. No schema migration or historical-data backfill is authorized.

## Decision 27: Upload security checks and file limits

**Status:** Company-owner policy recorded; security implementation details remain to be defined.

- **Failed or suspicious files:** Reject a file that fails security scanning or is suspected to be malicious, and do not retain it.
- **Unavailable or inconclusive scanning:** Block import until required checks pass or an authorized review resolves the issue.
- **Large files:** Warn when files are large but allow them to proceed. This decision does not define numeric file-size or complexity limits.
- **Implementation guardrails:** Define appropriate security checks and the authorized review path before implementation. Do not bypass required checks because scanning is unavailable or inconclusive. Specify operational limits separately if needed. No schema migration or historical-data backfill is authorized.

## Decision 28: Upload duplicates, conflicting values, and repeated submissions

**Status:** Approved by company owner.

- **Potential duplicate rows:** Flag potential duplicates for review and preserve both records until the issue is resolved. Do not automatically merge or silently discard either record.
- **Conflicting financial values:** Block a conflicting change to an existing financial record and require authorized review. Preserve the existing value and the proposed change for review.
- **Repeated uploads:** Detect repeated submissions of the same upload, prevent duplicate financial effects, and record the repeated attempt in the audit trail.
- **Implementation guardrails:** Define duplicate/conflict detection criteria and a stable upload/source identity for idempotency before implementation. No schema migration or historical-data backfill is authorized.

## Decision 29: Payment allocation order

**Status:** Approved by company owner.

- Use an explicitly configured, deterministic allocation order when multiple invoices are eligible.
- Show the proposed allocation for authorized confirmation before applying it.
- Define the order, preview, authorization, and audit behavior before implementation. No schema migration or historical-data backfill is authorized.

## Decision 30: Payment and invoice balance mismatch

**Status:** Approved by company owner.

- Flag a mismatch between a payment and an invoice's outstanding balance for review.
- Do not mark an invoice paid without explicit authorized settlement confirmation.
- Preserve the mismatch and its review outcome in the audit trail. No schema migration or historical-data backfill is authorized.

## Decision 31: Supported transaction workflows

**Status:** Approved by company owner.

- Support explicit money-in and money-out transactions, refunds, reversals, account transfers, and fees as distinct transaction types or linked workflows.
- Define relationships and balance/reporting effects for each workflow before implementation. No schema migration or historical-data backfill is authorized.

## Decision 32: Invoice lifecycle transitions

**Status:** Approved by company owner.

- Document permitted invoice state transitions.
- Paid status requires authorized confirmation; every state transition must be audited.
- Define transition permissions and audit events before implementation. No schema migration or historical-data backfill is authorized.

## Decision 33: Corrections to finalized financial records

**Status:** Approved by company owner.

- Preserve the finalized original and create a linked correction.
- Block the correction when its treatment or required approval remains unresolved.
- Maintain an audit link between original and correction. No schema migration or historical-data backfill is authorized.

## Decision 34: Reopening finalized reporting periods

**Status:** Approved by company owner.

- Reopening requires a specifically authorized role, a recorded reason, an audit trail, and preserved period close/reopen history.
- Define role permissions and evidence requirements before implementation. No schema migration or historical-data backfill is authorized.

## Decision 35: Reporting approved corrections

**Status:** Approved by company owner.

- Preserve finalized-period history.
- Show approved linked corrections in the current open period rather than automatically rewriting finalized-period figures.
- Keep the original and correction relationship visible and auditable. No schema migration or historical-data backfill is authorized.

## Decision 36: Discrepancy severity and blocking

**Status:** Approved by company owner.

- Apply documented severity rules to discrepancies.
- Block material or safety-critical operations and disclose unresolved discrepancies.
- Define severity thresholds, blocking behavior, resolution authority, and report qualification before implementation. No schema migration or historical-data backfill is authorized.

## Decision 37: Unknown legacy values in reports

**Status:** Approved by company owner.

- Preserve and flag unknown or ambiguous values.
- Qualify affected totals and do not infer missing values or silently exclude affected records.
- Define visible indicators and qualification rules before implementation. No schema migration or historical-data backfill is authorized.

## Decision 38: Import files containing blocking errors

**Status:** Approved by company owner.

- Reject the entire file if any row has a blocking error.
- Make no financial changes from a rejected file.
- Present rejected rows and reasons in the preview/audit evidence. No schema migration or historical-data backfill is authorized.

## Decision 39: Permitted import warnings

**Status:** Approved by company owner.

- Show permitted warnings in the upload preview.
- Require explicit confirmation by an authorized user before applying the import.
- Define which warnings are permitted and which must block import. No schema migration or historical-data backfill is authorized.

## Decision 40: Partial import failure and recovery

**Status:** Approved by company owner.

- Use atomic processing where possible.
- Where full atomicity is not technically possible, record partial outcomes, prevent duplicate financial effects on retry, and verify integrity before processing resumes.
- Document recovery, reconciliation, and retry behavior. No schema migration or historical-data backfill is authorized.

## Decision 41: Financial permissions and consequential actions

**Status:** Approved by company owner.

- Enforce permissions by operation and role.
- Require designated approval for consequential actions and maintain an audit trail.
- Define the role/action matrix and approval events before implementation; do not invent monetary thresholds. No schema migration or historical-data backfill is authorized.

## Decision 42: Financial notification delivery failures

**Status:** Approved by company owner.

- Log notification delivery failures and retry under a defined policy.
- Expose unresolved failures to authorized operators.
- Define retry limits/backoff and operator visibility before implementation. No schema migration or historical-data backfill is authorized.

## Decision 43: Financial upload audit evidence

**Status:** Approved by company owner.

- Record upload identity, uploader, timestamp, preview and confirmation, import outcome, accepted/rejected row references, and resulting changes.
- Keep evidence sufficient to trace the upload and its financial effects, subject to applicable privacy and retention controls.
- Define the audit fields and access controls before implementation. No schema migration or historical-data backfill is authorized.

## Cross-cutting implementation controls

1. **No schema migration or historical-data backfill is authorized by these decisions.** Any later proposal requires audited field mappings, before/after totals, reconciliation tests, and a recovery plan, reviewed and approved before execution.
2. Preserve legacy values and financial events; do not silently rewrite, normalize, or redistribute them.
3. Obtain independent review and qualified Romanian accounting/tax specialist validation for applicable statutory treatment, including invoice tax treatment and payroll.
4. Convert approved policy into canonical data contracts, validation rules, tests, and dependent implementation issues before changing financial behavior.
5. Existing integer fields, rounding calls, free-text statuses, relationships, and dashboard groupings describe current code behavior; they do not prove approved accounting semantics.
