# Contractor and PFA invoice treatment decision template

**Status: operational design draft; not legal, tax or accounting approval.** This template distinguishes supplier-invoice processing from employee payroll and identifies decisions that must be approved from source evidence. It does not determine whether any counterparty is a PFA, employee, company, or other individual, and does not specify withholding, VAT, contribution, or income-tax treatment.

## 1. Scope and separation from employee payroll

- Employee payslips are managed by `EmployeePaySlip` and source-linked activity items. Their lifecycle and controls must not be reused as a substitute for contractor invoice processing.
- Supplier administration currently uses `Vendor`, `VendorContract`, and `VendorInvoice`. Payments are represented by `Transaction`; `PaymentAllocation` supports proposed, confirmed, reversed and rejected allocations, with invoice/fee allocation kinds.
- These records support operational linkage but do not alone prove legal classification, tax treatment, invoice validity, payment settlement, or statutory compliance.
- Counterparty type must be established from controlled source documents and reviewed by an authorized owner. Never infer it from a vendor display name, bank transfer description, or the mere presence of an invoice.

## 2. Existing model inventory

| Model | Existing fields / behavior observed | Limitation relevant to contractor treatment |
| --- | --- | --- |
| `Vendor` | Name, vendor category, start/end dates, website; soft deletion and activity logging. | No reviewed legal/tax classification contract was established by this model inventory. A category label or name is not sufficient evidence of PFA status. |
| `VendorContract` | Vendor ID, contract code, start/end dates, document field; soft deletion and activity logging. | Document field and dates do not by themselves provide a verified classification, approved tax policy, or immutable policy version. Confirm document access and retention. |
| `VendorInvoice` | Vendor and optional vendor-contract links, legacy and decimal amount fields, currency fields, month, status/status code, invoice number/identifier and invoice reference; activity logging. | Multiple legacy and decimal amount fields coexist. Net/VAT/gross semantics, source-of-truth precedence, invoice correction rules, tax category and status transitions require finance approval. Do not assume a particular field is authoritative without reconciliation. |
| `Transaction` | Financial account, invoice links, transaction type, amount/decimal amount, currency, payment method, timestamp and tax reference; activity logging. | A linked transaction is not automatically proof of cleared settlement. Sign, reversal, partial payment, bank evidence and currency semantics must be reconciled. |
| `PaymentAllocation` | Transaction, customer/vendor invoice target, allocation kind, decimal amount/currency, status, reason, creator/confirm/reverse/reject metadata. Validation requires a positive decimal amount, currency code, reason, supported state and exactly one invoice target for invoice allocations. | Existing allocation mechanics do not approve the legal/tax classification or establish all overpayment, FX, partial-settlement, refund and reconciliation policies. Do not infer paid status solely from the existence of a proposed allocation. |

Source files: `app/Models/Vendor.php`, `VendorContract.php`, `VendorInvoice.php`, `Transaction.php`, `PaymentAllocation.php`; related migrations and resource authorization should be reviewed before implementation.

## 3. Counterparty evidence and classification register

Maintain the real register in a restricted, access-controlled location outside Git. Do not commit names, national identifiers, tax identifiers, bank details, contract documents, invoice PDFs, or actual payment values to repository fixtures.

| Internal reference | Proposed category | Evidence source/reference | Effective date | Evidence checked by/date | Specialist decision/reference | Confidence/status | Unresolved questions / next owner |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Restricted record ID only | Unresolved until reviewed | Controlled evidence reference | Required | Required | Required where legal/tax interpretation applies | Pending | Required |

Possible categories are only a review vocabulary: legal-entity supplier, verified PFA, other individual, employee/CIM, or unresolved. The category list is not a legal test and does not determine tax treatment. Preserve `unresolved` as a valid state; block any path that depends on an unverified category.

## 4. Contract and invoice lifecycle decisions

For each decision, record owner, source evidence, approval date, effective date, test reference and change history.

| Decision | Questions that must be resolved | Approver | Status |
| --- | --- | --- | --- |
| Onboarding and classification | Which source documents establish legal identity and tax category? Who verifies them and records effective dates? | Finance/vendor owner and qualified Romanian adviser for legal/tax interpretation | Open |
| Contract linkage | Is a contract required? How are amendments, date ranges, renewals, termination and contract/invoice mismatches handled? | Contract owner / Finance | Open |
| Invoice identity and validity | Required invoice identifiers, issue/supply/due dates, currency, line details, attachments, correction/credit-note handling and duplicate detection | Finance/accounting | Open |
| Amount semantics | Which fields represent net, VAT, gross, and currency? How are legacy fields reconciled? What rules apply to rounding and foreign currency? | Finance/accounting | Open |
| Review and approval | Who prepares, reviews and approves invoices, and what evidence is required before payment? How is segregation of duties enforced? | Finance | Open |
| Payment and allocation | How are partial payments, multiple invoices, fees, FX, transfers, refunds, failed payments and reversals linked to bank evidence? | Finance operations/accounting | Open |
| Tax/withholding treatment | Which rules apply to the verified counterparty category and contract, for which effective dates, and which required declarations/reporting follow? | Qualified Romanian payroll/accounting/tax specialist | Open |
| Corrections and audit | How are invoice corrections and allocation reversals represented without erasing history? Who approves corrections after period close? | Finance/accounting | Open |
| Retention and access | Where are source documents held, who can view them, what audit events are retained, and what approved retention/deletion policy applies? | Data owner, Finance and qualified adviser | Open |

## 5. Required linked records and payment evidence

Before declaring an invoice settled, the approved workflow must define the evidence chain. At minimum, review whether the following references are required:

1. Verified counterparty record and effective-dated classification decision.
2. Applicable contract and amendments, with controlled document reference.
3. Invoice identity, source document, currency and approved amount semantics.
4. Review/approval record with actor, timestamp, decision and reason.
5. Bank transaction or other approved payment evidence.
6. Confirmed allocation(s) connecting payment evidence to the invoice, with reason and currency.
7. Reversal/correction link for rejected, reversed, refunded, or amended records.
8. Audit trail and policy/version reference for any tax/accounting decision that affects processing.

This is a proposed evidence checklist, not a claim that every item is currently implemented or that a particular legal requirement applies. Existing allocation statuses and relationships should be mapped to the approved lifecycle before behavior changes.

## 6. Validation and exception behavior

The approved workflow should explicitly define handling for:

- missing or expired classification evidence;
- invoice with no valid contract link, or dates outside contract coverage;
- duplicate invoice number or duplicate source document;
- disagreement between legacy amount fields and decimal amount fields;
- currency mismatch between invoice, transaction and allocation;
- proposed allocation mistaken for confirmed payment;
- over-allocation, underpayment, partial payment, fee, refund, reversal or ambiguous bank match;
- invoice correction after close and changes to source documents after approval;
- access denial for restricted counterparty identifiers or contract/invoice documents.

Where the approved policy is missing, the safe state is `unresolved` or `needs review`, not an inferred tax treatment or automatic payment approval. Do not implement statutory withholding or tax calculations from this template.

## 7. Synthetic fixture policy

Synthetic fixtures may test lifecycle states, invalid/missing evidence, duplicate detection, amount-field disagreement, allocation state transitions, authorization, and correction linkage. Use fictional IDs and decimal strings. Label each fixture synthetic, keep it free of actual personal data, and do not include expected tax/withholding results unless a qualified reviewer has approved the policy and expected values in a versioned decision record.

## 8. Approval and implementation gate

Before contractor/PFA implementation beyond documentation and safe validation tests:

- [ ] Real counterparties and contracts are inventoried from controlled evidence (#335).
- [ ] Each counterparty classification is owner-reviewed; unknowns remain explicit.
- [ ] Current Romanian requirements are reviewed by a qualified specialist (#336).
- [ ] Employee payroll policy and input contracts are approved (#337 and #338), with a clear boundary from invoice processing.
- [ ] Finance approves invoice status transitions, amount-field precedence, approval roles and close behavior.
- [ ] Payment allocation and settlement evidence rules are approved, including partial payments and reversals.
- [ ] Any tax/withholding treatment and expected outputs have dated source references and specialist-approved fixtures.
- [ ] Authorization, audit, privacy, correction, retention and recovery tests pass.

**Current status: blocked for legal/tax behavior and production classification.** This document is a review checklist. No named specialist approval record, real counterparty evidence, or approved tax calculation fixtures are included here.