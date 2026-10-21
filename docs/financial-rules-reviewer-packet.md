# Financial Rules Reviewer Packet

**Repository:** deputy-proxy/Management-OS
**Primary issue:** [#304 - Approve financial rules and monetary semantics](https://github.com/deputy-proxy/Management-OS/issues/304)
**Policy register:** [Financial Rules Decision Register](financial-rules-decision-register.md)
**Readiness matrix:** [Financial Rules Implementation Readiness](financial-rules-implementation-readiness.md)
**Purpose:** Obtain documented, scoped specialist review of policy assumptions that cannot be validated by company-owner approval or software tests alone.

> **Scope warning:** This packet requests review and written findings. It does not certify compliance, authorize a schema migration, authorize historical-data conversion/backfill, authorize destructive deletion, or authorize a production financial-behavior change. Those actions remain blocked until the relevant review and technical approval gates are completed.

## 1. Approval state and boundaries

### Company-owner approval recorded

The company owner confirmed on **2026-10-21**:

- Decisions **1–43** are approved as intended company business policy, subject to applicable specialist review.
- A qualified Romanian reviewer is available.
- The next work product is this reviewer packet.

The owner's name/signature has not been supplied in this packet. Add it if formal governance requires it.

### Reviewer confirmation recorded

On **2026-10-21**, the owner supplied the following reviewer details and reported conclusion:

- **Reviewer:** Catalin Contes
- **Qualification stated:** Certified Accountant
- **Review date:** 21.10.2026
- **Conclusion reported by owner:** Everything is correct; no conditions or requested changes.

The owner attests that the company email stored in cloud storage, the accountant's scope information, and the separate specialist-review details are authentic. The email URL/message identifier and itemized reviewer details are not reproduced in this repository and have not been independently inspected. The owner describes the accountant's scope as accounting/financial semantics and confirms the authenticity of the reported Romanian tax/invoicing, statutory payroll, retention/deletion legal-accounting, privacy, and upload-security review information. Record this as an explicit owner attestation, not as an independent audit or compliance certification.

| Approval | Status | Boundary |
|---|---|---|
| Company-owner policy, Decisions 1–43 | Approved in conversation on 2026-10-21 | Business policy only; not independent or statutory approval |
| Accountant review | Owner attests email and accounting/financial-semantics scope information are authentic | Exact decision/topic list and retrievable email locator are not reproduced in this repository |
| Romanian tax/invoicing review | Owner attests the reported review information is authentic | Underlying reviewer details are not reproduced here; this is not an independent statutory-treatment audit |
| Statutory payroll review | Owner attests the reported review information is authentic | Underlying reviewer details and expected-result cases are not reproduced here; downstream payroll safeguards remain applicable |
| Retention/deletion legal-accounting review | Owner attests the reported review information is authentic | Detailed reviewer record, category schedule, and approved safeguards are not reproduced here |
| Privacy review | Owner attests the reported review information is authentic | Detailed reviewer record and safeguards are not reproduced here |
| Upload-security review | Owner attests the reported review information is authentic | Detailed reviewer record and controls are not reproduced here |
| Canonical technical contract | Pending downstream work | Must map policy to field meanings, validation, compatibility, authorization, and audit behavior |
| Migration/backfill/destructive changes | **Not authorized** | Requires a separately reviewed and approved plan, data evidence, reconciliation, and recovery plan |

### Owner-reported evidence update (2026-10-21)

- **Evidence type:** Company email, reportedly stored in cloud storage.
- **Evidence locator:** Not supplied to this repository; the email was not available for direct inspection during this closure check.
- **Accountant scope:** Accounting/financial semantics; exact topics or decision numbers not supplied.
- **Other review areas reported complete:** Romanian tax/invoicing, statutory payroll, retention/deletion legal-accounting, privacy, and upload security.
- **Evidence limitation:** The owner has explicitly attested that the email and review details are authentic. Their locators and itemized details are not reproduced here, so this packet does not claim independent inspection or audit.

## 2. Instructions for reviewers

1. Review the decision register and readiness matrix before completing this packet.
2. Complete only the section within your professional remit. Mark unrelated questions **Out of scope**, not approved.
3. For each conclusion, cite the relevant decision number, source document, legal/accounting guidance, or other evidence. State the jurisdiction and effective date of any rule relied upon.
4. Choose one outcome for each review area: **Approved**, **Approved with conditions**, **Not approved**, or **Out of scope**.
5. List conditions as specific, testable actions with an owner and evidence needed to close them.
6. Do not infer that a software test, current field name, or existing application behavior proves accounting or statutory correctness.
7. Return the completed packet to the company owner/project team. Record unresolved items as blockers; do not silently resolve them in code.

### Reviewer identification

- **Reviewer name:** Catalin Contes
- **Professional role / qualification:** Certified Accountant
- **Organization / practice (if applicable):** Not provided
- **Review date:** 21.10.2026
- **Jurisdiction and scope covered:** Owner attests the reported accounting/financial-semantics scope information is authentic; exact decision/topic coverage is not reproduced here
- **Documents/evidence reviewed:** Financial rules reviewer packet / Decisions 1–43, as reported by the owner
- **Reported conclusion:** Everything is correct; no conditions or requested changes
- **Written evidence reference:** Company email stored in cloud storage; owner attests authenticity, but no retrievable link/message ID or attachment is reproduced in this repository

## 3. Romanian accounting and tax review

**Intended reviewer:** Qualified Romanian accountant / tax or invoicing specialist.
**Decision register areas:** Decisions 1–11, 13, 16–17, 20–22, 29–37, plus any related decisions relevant to the reviewer's remit.

### Questions requiring explicit answers

**A1. Invoice VAT treatment (Decisions 3 and 9)**

The owner policy states that company invoices will never have VAT. This is a business assertion, not evidence of a statutory exemption.

- Is this treatment legally appropriate for the company's actual activities, registration, counterparties, and relevant transaction types under the applicable Romanian rules?
- If yes, what conditions, exceptions, invoice wording, evidence, and effective date apply?
- If no, what exact scenarios require different treatment and what policy must be changed before implementation?
- Does the rule differ for sales, purchases, cross-border transactions, credit notes, or other relevant cases?

**Finding / evidence / conditions:**
____________________________________________________________________
____________________________________________________________________

**A2. Canonical invoice amounts and legacy values (Decisions 1, 3, 9–11)**

- Define the business/accounting meaning and unit for each applicable field: customer invoice `amount_without_vat`, `vat`; vendor invoice `amount_without_vat`, `vat`, `amount`, and `currency`.
- Which field is authoritative for the amount receivable/payable, and how must inconsistent fields be flagged?
- What evidence is required before interpreting legacy integer values as major or minor currency units?
- How should unknown legacy currency and unknown historical field meaning remain represented?
- What is the approved treatment for discounts, credit notes, corrections, and rounding differences?

**Finding / field-by-field definitions / conditions:**
____________________________________________________________________
____________________________________________________________________

**A3. Currency, precision, and rounding (Decisions 1, 8, 10–11)**

- Confirm whether RON default for new records and explicit currency for other currencies are appropriate.
- Confirm two-decimal persisted monetary precision, higher intermediate invoice calculation precision, and final-total half-up rounding; specify any workflow-specific exceptions.
- Define how cross-currency transfers should be documented, including source amount, destination amount, exchange rate, rate date/source if needed, and fees.
- Confirm that no automatic FX conversion and no guessing/conversion of legacy values is acceptable.

**Finding / examples / conditions:**
____________________________________________________________________
____________________________________________________________________

**A4. Transactions, settlement, and reporting (Decisions 2, 4–6, 8, 20–21, 29–35)**

- Validate the accounting implications of explicit money-in/money-out direction, non-negative amounts, separate linked refunds/reversals, transfer-linked account entries, and separately recorded fees.
- Confirm the one-full-balance-payment workflow and identify any business or legal scenarios where partial payments must be supported.
- Confirm what evidence is required before an invoice can be marked paid and how a payment/invoice mismatch should be resolved.
- Define the accounting implications of cash versus accrual operational views and which event date is appropriate for each proposed report.
- Confirm the approach of preserving finalized-period history and recording approved linked corrections in the current open period.

**Finding / exceptions / conditions:**
____________________________________________________________________
____________________________________________________________________

**A5. Product costs, allocation, and reconciliation (Decisions 7, 13, 16, 36–37)**

- Validate direct-cost assignment and the rule that overhead allocations must total exactly 100%.
- Confirm how rounding residuals and unallocated amounts should be represented.
- Identify which discrepancies should block operations versus warn, and what evidence supports those severity levels.
- Confirm what can be called an operational consistency check and what must not be described as statutory accounting reconciliation.

**Finding / thresholds or examples / conditions:**
____________________________________________________________________
____________________________________________________________________

### Accounting/tax reviewer decision

- [ ] Approved
- [ ] Approved with conditions
- [ ] Not approved
- [ ] Out of scope

**Conditions / required changes / supporting references:**
____________________________________________________________________
____________________________________________________________________

**Reviewer name and qualification:** __________________________________
**Signature or written approval reference:** ____________________________
**Date:** __________________

## 4. Romanian payroll review

**Intended reviewer:** Qualified Romanian payroll/accounting specialist.
**Decision register area:** Decision 12 and any payroll-adjacent permissions, corrections, retention, or reporting rules.

### Questions requiring explicit answers

**P1. Calculation scope and authoritative inputs**

- Identify which inputs, rates, deductions, effective dates, employee categories, and employer-specific rules are required for a lawful payroll calculation.
- Which existing calculations are only operational estimates and must not be treated as statutory results?
- What source evidence and effective-dated rule version are required for each finalized calculation?

**P2. Finalization and correction**

- What review/authorization evidence is required before payroll results can be finalized or released?
- How must corrections to finalized results be represented, approved, and linked to the original?
- What circumstances require a period correction versus a current-period adjustment under applicable rules?

**P3. Test cases and release gate**

- Supply or approve representative examples with expected results, including relevant edge cases and effective dates.
- Identify any statutory filing, payment, or release checks that must remain blocked until verified.
- State which assumptions must be re-reviewed when Romanian rules change.

**Finding / approved rule source / examples / conditions:**
____________________________________________________________________
____________________________________________________________________

### Payroll reviewer decision

- [ ] Approved
- [ ] Approved with conditions
- [ ] Not approved
- [ ] Out of scope

**Conditions / required changes / supporting references:**
____________________________________________________________________
____________________________________________________________________

**Reviewer name and qualification:** __________________________________
**Signature or written approval reference:** ____________________________
**Date:** __________________

## 5. Legal, privacy, and security review

**Intended reviewer(s):** Romanian legal/privacy specialist and security reviewer, as applicable.
**Decision register areas:** Decisions 14, 17, 22, 24–28, 38–43.

### Questions requiring explicit answers

**L1. Financial-record deletion and retention (Decisions 14 and 22)**

- Define retention obligations by record category and any applicable limitation, deletion, anonymization, or legal-hold requirements.
- Which records may be archived indefinitely, and which may not?
- What procedure must handle deletion requests that conflict with legal retention?
- What approval, reason, evidence, and audit history are required before deleting used financial or payroll records?
- What is the approved distinction between archiving, hiding from operational views, anonymization, and permanent deletion?

**L2. Original upload files and access (Decisions 25–27)**

- Is blanket indefinite retention of all original upload files permissible? If not, specify category-based periods and exceptions.
- Is access by every user with general financial-record access appropriate, or must source files have narrower permissions?
- Are view/download audit events required for any categories or circumstances?
- Define minimum controls for file scanning, quarantine/rejection, unavailable or inconclusive scans, malicious-file non-retention, and evidence retention.
- Identify personal, confidential, or otherwise sensitive data that may appear in uploaded files and the required safeguards.

**L3. Import authorization and audit trail (Decisions 24, 28, 38–43)**

- Confirm the required approval and audit evidence for preview, confirmation, accepted/rejected rows, resulting changes, and repeated uploads.
- Identify any mandatory retention or access restrictions for import evidence.
- Define whether and when review of conflicting financial values must be separated from the person who submitted the upload.

**Finding / applicable requirements / conditions:**
____________________________________________________________________
____________________________________________________________________

### Legal/privacy/security reviewer decision

- [ ] Approved
- [ ] Approved with conditions
- [ ] Not approved
- [ ] Out of scope

**Conditions / required changes / supporting references:**
____________________________________________________________________
____________________________________________________________________

**Reviewer name and qualification:** __________________________________
**Signature or written approval reference:** ____________________________
**Date:** __________________

## 6. Technical contract review (engineering, after specialist findings)

This section translates approved policy into an implementable contract. It does not replace the specialist reviews above.

For every affected field or workflow, document:

- Canonical field name and business meaning.
- Currency, unit, precision, nullability, and explicit unknown behavior.
- Validation rules, state transitions, and permitted operations.
- Source-of-truth field and compatibility mapping for every observed legacy value.
- Authorization role, designated approver where required, reason/evidence, and audit event.
- Error severity, blocking behavior, preview/confirmation requirements, and recovery behavior.
- Representative valid, invalid, ambiguous, duplicate, conflict, rounding, correction, and retry examples with expected outcomes.
- Readers/writers affected, including forms, dashboards, reports, imports/exports, commands, notifications, and payroll.
- Tests and read-only integrity checks needed to verify the contract.

### Technical sign-off checklist

- [ ] Every specialist condition has a linked owner and a documented resolution or remains an explicit blocker.
- [ ] Field meanings and units are specified without guessing.
- [ ] Every legacy value/status/type has an approved mapping or explicit unknown/blocked treatment.
- [ ] Representative examples have expected outcomes approved by the appropriate reviewer.
- [ ] Authorization, audit, idempotency, failure, and recovery behavior is specified.
- [ ] Read-only baseline and reconciliation evidence is captured before any migration proposal.
- [ ] Any proposed migration includes row counts, totals by currency/status/period, before/after comparison, exception handling, tested recovery, and explicit approval.
- [ ] The migration/backfill/destructive-change approval is documented separately before execution.

**Engineering owner:** _______________________ **Date:** ______________
**Contract version / PR:** ___________________________________________

## 7. Consolidated conditions and approval log

Use one row per unresolved item. Do not mark an item resolved without linking the evidence.

| ID | Decision / topic | Required action or evidence | Owner | Status / due date | Evidence link |
|---|---|---|---|---|---|
| R-01 | VAT / invoice treatment | Owner attests reported review information is authentic; retain applicable statutory safeguards | Romanian tax/invoicing specialist | Owner-confirmed |  |
| R-02 | Invoice field meanings / payable total | Field-by-field reviewed contract | Finance owner + engineering owner (#306) | Blocked |  |
| R-03 | Currency / precision / rounding | Approved workflow-specific examples | Accountant for applicable accounting scope + engineering owner (#306/#307) | Blocked |  |
| R-04 | Payment / transfer / correction treatment | Written accounting review and state rules | Accountant for applicable accounting scope + finance/engineering owners | Blocked |  |
| R-05 | Payroll calculations / statutory release | Owner attests reported review information is authentic; statutory release must still follow applicable safeguards | Qualified Romanian payroll specialist + payroll engineering owner | Owner-confirmed |  |
| R-06 | Retention / deletion / source-file access | Owner attests reported review information is authentic; retain applicable legal/privacy safeguards | Romanian legal/accounting reviewer + privacy owner | Owner-confirmed |  |
| R-07 | Upload security / audit / recovery | Owner attests reported review information is authentic; required controls remain applicable | Security owner + privacy/legal reviewers + engineering owner | Owner-confirmed |  |
| R-08 | Legacy field mapping | Audited mappings or explicit unknown treatment | Engineering/data owner (#306) + finance owner | Blocked |  |
| R-09 | Migration proposal | Before/after totals, reconciliation, recovery and separate approval | Engineering/data owner (#307) + finance owner; separate approval required | Not authorized |  |

### Final review disposition

- [ ] All relevant specialist areas approved without conditions (requires review evidence and scope to be recorded).
- [ ] Approved with conditions; implementation may proceed only for explicitly cleared scope.
- [ ] Not approved; listed policy decisions require revision.
- [x] Owner explicitly attests that the company email, accountant-scope information, and specialist-review details are authentic. This records the owner's confirmation for #304; it is not an independent audit or compliance certification.

**Approved scope (be precise):**
____________________________________________________________________

**Outstanding blockers and accountable owners:**
____________________________________________________________________

**Company-owner acknowledgment of specialist findings:**
Name: ______________________________ Date: _________________________
Signature / approval reference: ______________________________________

## 8. Non-negotiable project guardrails

- This packet is a review artifact, not a compliance certificate.
- Company-owner approval of Decisions 1–43 is recorded separately from specialist approval.
- Do not infer VAT exemption or statutory payroll correctness from owner policy or application behavior.
- Preserve original financial records and unresolved values; do not silently normalize or rewrite history.
- Do not perform schema migrations, amount conversion, historical backfill, destructive deletion, or broad financial behavior changes until the relevant specialist review, canonical contract, migration/recovery plan, and separate authorization are documented.
- Follow repository workflow: dedicated issue branch, focused and full validation appropriate to documentation changes, PR review, and actual green CI before merge.