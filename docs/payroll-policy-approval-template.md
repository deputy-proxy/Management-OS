# Romanian payroll policy approval template

**Status: blank approval template only. No statutory rule, rate, threshold, exemption, or expected result is approved by this document.** Complete it with Finance/HR and a qualified Romanian payroll/accounting specialist. Do not enable statutory calculation until the required approvals and independently checked test vectors are recorded.

## 1. Approval record

| Field | Required value |
| --- | --- |
| Policy identifier and version | To be assigned by policy owner |
| Status | Draft / In review / Approved / Superseded |
| Prepared by and date | Required |
| Finance/HR approver and date | Required for company-specific choices |
| Qualified Romanian payroll/accounting reviewer and date | Required for statutory interpretation and expected outputs |
| Independent verifier and date | Required for expected-result reconciliation |
| Effective-from / effective-to | Explicit dates; open-ended end date only if approved |
| Authoritative source register | Source title, issuing authority, version/publication date, relevant provision, URL or controlled reference, effective date, review date |
| Supersedes | Prior policy version or `None` |
| Change summary and rationale | Required; distinguish legal change from company choice |
| Approval evidence location | Controlled reference; do not place personal data or signed employee documents in Git |

Approval must identify actual people/roles and evidence in the controlled record. A blank template, repository author, or passing software tests is not domain approval.

## 2. Rule register

Create one row per rule or decision. Do not combine statutory requirements and discretionary company benefits in the same rule.

| Rule ID | Rule category | Decision / formula description | Authoritative source and provision | Effective dates | Required input fields | Missing/invalid input behavior | Precision and rounding stage | Exceptions / boundaries | Owner | Reviewer status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TBD | TBD | Not specified | Required before approval | Required | Required | Required | Required | Required | Required | Pending |

Allowed rule categories should be assigned by reviewers, for example statutory contribution, income tax, taxable base, exemption/deduction, company-specific benefit, time/absence treatment, correction, or reporting. These labels are organizational only and do not assert that a given rule applies.

For every rule, record:

- the authoritative source and the exact effective-date interval;
- the calculation order and dependencies on other rules;
- all required inputs, source-of-truth and validation;
- units, currency, precision, rounding method and stage;
- boundary dates, missing data, conflicting data and correction behavior;
- how the calculation explanation and rule version are retained with the result;
- tests that prove the behavior and the reviewer who approved expected outputs.

## 3. Calculation sequence

The specialist must supply and approve the sequence. Do not infer it from existing application fields.

| Step | Input components | Operation and rule IDs | Output component | Rounding point | Reviewer approval |
| --- | --- | --- | --- | --- | --- |
| TBD | TBD | Not specified | TBD | Not specified | Pending |

The final sequence must distinguish at least the concepts the reviewer says are applicable, such as gross components, taxable bases, contributions, income tax, deductions, employer-side components, and net result. The existence of a row in this template does not establish a legal requirement or the applicable treatment.

## 4. Expected-result test-vector register

Each vector must be supplied or checked by the qualified reviewer and independently reconciled. The test fixture should contain synthetic identifiers and only the minimum data needed to exercise the rule. Do not use actual employee records or sensitive documents in repository fixtures.

| Vector ID | Scenario / purpose | Policy version | Effective date | Input fixture reference | Expected component values | Expected final result | Boundary / rounding assertion | Reviewer | Independent check | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TBD | Not yet specified | TBD | TBD | TBD | Not supplied | Not supplied | Not specified | Required | Required | Pending |

Required coverage, as applicable and as approved by the specialist:

- effective-date start/end and a policy change between adjacent periods;
- mid-period hire, termination, or contract-term change;
- each approved salary basis and schedule/norm;
- each applicable leave/absence and benefit/allowance category;
- threshold/boundary values and rounding ties;
- missing, invalid, expired or conflicting inputs;
- duplicate source inputs and repeated calculation attempts;
- correction after close and recalculation using a historical policy version;
- authorization and access-denial behavior.

For each vector, retain input values, rule/policy version, expected intermediate components, expected final values, explanation, reviewer approval and independent reconciliation. Expected values must not be generated solely by the implementation under test.

## 5. Workflow, access and correction decisions

| Decision | Approved behavior | Accountable owner | Evidence / test reference | Status |
| --- | --- | --- | --- | --- |
| Who may prepare a period | Pending | Finance/HR | Pending | Open |
| Who reviews and approves it | Pending | Finance/HR | Pending | Open |
| Who may mark a period Paid and what evidence is required | Pending | Finance | Pending | Open |
| Required audit events and sensitive-field access | Pending | Data/security owner | Pending | Open |
| Policy version attached to a period/result | Pending | Payroll owner / engineering | Pending | Open |
| Historical recalculation and correction policy | Pending | Finance/HR and specialist | Pending | Open |
| Record retention and export/recovery | Pending | Data owner / legal adviser | Pending | Open |
| Shadow-run reconciliation and release gate | Pending | Finance/HR and specialist | Pending | Open |

The current payslip workflow already has `Open -> Closed -> Paid`, immutable source-linked rows, duplicate-source protection and signed adjustments. Any approved policy or implementation must preserve these controls or explicitly document a reviewed replacement. A statutory result must not be silently written into the legacy JSON breakdown or manual baseline.

## 6. Explicit non-decisions

Until completed and approved, this record does not decide:

- which statutory rates, thresholds, bases, exemptions, deductions or reporting requirements apply;
- the applicability of a tax or contribution rule to any individual arrangement;
- the legal classification of an employee, PFA, other individual or supplier entity;
- rounding stages, proration, leave/overtime treatment or company benefit taxation;
- any expected statutory net-pay amount;
- whether existing legacy amounts can be safely migrated or interpreted as gross/net/statutory components.

Unresolved questions must remain visible and block affected calculation paths. Do not fill blanks with generic defaults.

## 7. Release gate

Before statutory engine work is authorized, all of the following must be true:

- [ ] Worker arrangements and real contract facts are reconciled from controlled source evidence (#335).
- [ ] Current official requirements are reviewed for the relevant effective dates (#336).
- [ ] Finance/HR and qualified specialist approve this versioned policy and its source register (#337).
- [ ] Required payroll input contract and schema gaps are approved (#338).
- [ ] Every required test vector has reviewer-approved expected values and an independent check.
- [ ] Authorization, audit, correction, period close and historical policy-version behavior are approved.
- [ ] Shadow-run results reconcile with specialist-approved calculations and the recovery plan is tested.

**Current status: blocked.** This repository context does not contain a named specialist's approval record or approved statutory expected-result fixtures. This template supports the review process; it does not satisfy the approval acceptance criteria by itself.