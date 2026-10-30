# Payroll period inputs

Payroll period inputs capture source-backed leave, absence, overtime, allowance, bonus, earning, deduction, and informational records for one employee contract and one complete calendar-month payroll period.

## Capture and review

Each record stores its effective dates, category, entry type, description, optional quantity/unit and amount/currency, source type/reference, recorder, and review metadata. Submitted records require a source link. Approval requires a source amount or quantity and a source link. Draft and rejected records may be corrected and resubmitted; submitted records are reviewed through the application service. Approved records are immutable. Corrections create a new draft linked to the original record; they do not rewrite the original.

Source references are unique within a contract and payroll period. Leave and absence date ranges cannot overlap. Effective dates must be within the associated monthly period and linked contract. Inputs cannot be added to a closed or paid payslip period; corrections must be recorded in a later open period. The application rejects malformed periods, reversed date ranges, mismatched employee/contract pairs, duplicate source links, and unsupported categories or entry types.

## Calculation boundary

Payroll calculation runs automatically capture period inputs in their immutable input snapshot, including source links, review status, reviewer, and correction linkage. A period input that is not approved blocks the run. An approved period input also blocks monetary calculation with `period_input_policy_unapproved` until a specialist-approved, effective-dated category rule is available. No statutory leave proration, absence treatment, overtime multiplier, allowance treatment, bonus treatment, or deduction rule is inferred by this feature. Missing source values and missing source links are explicit blocking reasons.

This is deliberate: capture, approval, source traceability, and reproducible snapshots are implemented independently of statutory calculations. Adding a calculation rule requires specialist approval, a versioned policy definition, and synthetic regression coverage before the rule can affect a payslip.

## Permissions

The `payroll_period_input.index`, `.create`, `.update`, `.submit`, and `.approve` abilities control access. The service enforces submit and review permissions server-side; UI visibility is not the security boundary.
