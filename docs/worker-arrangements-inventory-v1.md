# Worker arrangements: current-state inventory v1

**Status:** Engineering inventory; worker classifications and statutory treatment are not approved by this document  
**Roadmap:** [Issue #335](https://github.com/deputy-proxy/Management-OS/issues/335)

## Evidence reviewed

This inventory is based on the repository models inspected for this audit and the existing issue requirements. It is intentionally a bounded inventory, not a legal classification of any individual.

- `App\\Models\\Employee` represents an employee record with `full_name`, `first_name`, `last_name`, `user_id`, `date_of_birth`, and a `job_description` relationship. It uses soft deletes and activity logging. The model does not, by itself, define employment-contract type, effective dates, salary basis, statutory tax inputs, or an employee/contractor classification enum.
- `App\\Models\\User` is the authentication identity. An `Employee` may belong to a `User`; that relationship is not proof of legal worker status and should not be used to infer payroll eligibility.
- `App\\Models\\Vendor` is a separate soft-deletable, activity-logged model. Its existence supports a distinct vendor record, but does not prove that every vendor is a PFA, that a vendor has an approved contract, or that a vendor invoice is eligible for a particular tax/payment treatment.
- `App\\Models\\Transaction` is an operational financial record with legacy and canonical amount/currency fields. A transaction alone does not establish an employee payroll liability, contractor invoice, approved payable, or settled payment.
- The roadmap's existing payslip workflow description says payslips are activity-based and have snapshots, duplicate protection, period locking, and correction controls. It also states that statutory CAS, CASS, income tax, and related calculations are not automated. This inventory does not independently certify every implementation detail; those controls need to be traced to their owning models, services, migrations, and tests before changes are made.

## Current-state arrangement map

| Arrangement or record | Evidence in the current model surface | Safe conclusion | Unresolved |
| --- | --- | --- | --- |
| Employee record | `Employee` model and optional `User` relationship | Employee data is represented separately from the login identity | Employment status, contract type, effective dates, compensation terms, statutory inputs, and authoritative source |
| Vendor / contractor candidate | `Vendor` model | A vendor record exists as a separate domain concept | Whether the vendor is a natural person, PFA, company, or another supplier; contract/invoice lifecycle and approved classification |
| Activity-based payslip | Roadmap issue context | Existing workflow is intended to preserve snapshots and period/correction controls | Exact tables, fields, policy-version linkage, calculation provenance, and verified test coverage |
| Invoice and payment records | Financial model/roadmap context | Invoice, transaction, and worker concepts must be reconciled through explicit links | Canonical invoice model, payable ownership, payment matching, partial settlement, reversal, and source-of-truth rules |
| Other engagement types | No authoritative classification established by the inspected models | Do not infer an arrangement from a name, vendor record, login, or transaction | Whether employees, PFA contractors, companies, temporary workers, or other arrangements are actually used |

## Required source-of-truth and lifecycle decisions

Before implementing payroll or contractor calculations, record owner-approved answers to the following:

1. **Classification authority:** who determines worker type, which approved document is authoritative, and how effective-dated changes are recorded.
2. **Employee lifecycle:** onboarding, contract changes, leave/absence inputs, termination, period close, correction, and retention.
3. **Contractor lifecycle:** vendor identity and legal form, contract approval, invoice receipt/validation, payable approval, payment matching, partial payment, rejection, reversal, and correction.
4. **Source links:** stable links from worker/contract to period inputs, payslip or invoice, payable, bank transaction, approval, and audit event.
5. **Versioning:** immutable or reconstructable inputs and policy versions used for each closed period; corrections must not silently overwrite historical results.
6. **Access and privacy:** least-privilege access to personal identifiers, compensation, bank/payment details, and audit history; avoid exposing sensitive fields in logs and broad dashboards.
7. **Failure handling:** missing or contradictory worker classification, missing contract or invoice, duplicate submissions, late corrections, unlinked payments, and unsupported arrangements must be held for review rather than guessed.

## Guardrails

- Employee statutory payroll and PFA/vendor invoice/payment processing are separate workflows. Do not reuse employee payslip calculations for a contractor.
- Do not hard-code statutory rates, thresholds, or legal assumptions based on this engineering inventory.
- Do not infer that a vendor is a PFA, that an employee record represents a currently active employment relationship, or that a transaction proves settlement.
- Preserve existing payslip snapshots, duplicate protection, period locks, correction history, and audit records while verifying their implementation.
- Compliance-sensitive classifications and calculations require qualified Romanian payroll/legal/accounting review. This document does not claim such review has occurred.

## Follow-up evidence needed

- Trace payslip, contract, invoice, and payment models plus migrations, services, permissions, and tests.
- Identify the exact source and relationships for worker contracts and effective-dated terms.
- Confirm with the responsible owner which engagement types are actually used and approve the classification vocabulary.
- Produce synthetic lifecycle fixtures for employee period close/correction and contractor invoice/payment/reversal before implementation work.
- Record qualified review and the approved input/policy contract before statutory calculation work begins.

## Acceptance status

This is a preliminary evidence-bounded inventory. It documents known model boundaries and explicit unknowns, but does not satisfy owner review or qualified compliance review. Downstream payroll-input and contractor implementation must remain gated until those decisions and source mappings are recorded.
