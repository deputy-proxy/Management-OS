# Effective-dated employee contract payroll inputs

## Purpose

`employee_contract_payroll_inputs` stores a versioned set of typed payroll inputs for an employee contract. It supplements the legacy `employee_contracts` columns without changing their historical meaning. Existing salary fields and clause JSON are not silently converted into the new contract.

## Input contract

- `effective_from` is required; `effective_until` is optional and inclusive. Periods for the same contract may be adjacent but may not overlap.
- Each input period must fall within the linked employee contract's effective dates.
- `salary_basis` is optional and restricted to the configured values `monthly`, `hourly`, `daily`, `annual`, or `other`. A blank value is unknown, not a default.
- `currency_code` is optional and, when present, must be a three-letter uppercase code. The application does not infer a currency for legacy integer salary fields.
- `scheduled_hours_per_week` is optional and constrained to 0 through 168. It is an input value, not a statutory rule.
- `work_schedule` is structured JSON for non-sensitive schedule details. Do not store medical or identity documents here.
- `allowance_inputs` is a list of explicit code/amount/currency entries. Values are not calculated or interpreted as approved statutory categories by this model.
- `tax_status_code` is an optional configured code. Blank means unknown. This field does not infer eligibility or calculate tax treatment.
- `source_reference` is a short reference to a controlled document or source, not the document content itself.
- `input_status` is `unverified`, `verified`, or `rejected`. Verified entries require a reviewer and timestamp. Verification only describes the record's input-review state; it does not certify legal correctness.

## Historical and privacy behavior

Input records may be soft-deleted for correction/recovery, but permanent deletion is blocked. Verified input values are immutable; only shortening their effective end date is allowed to make room for a successor version. Supersede an input by ending its effective period and adding a new record. The parent contract resource's server-side authorization controls access and mutation. Activity logs omit raw tax codes, source references, schedule JSON, and allowance payloads to reduce exposure of sensitive values.

Unknown or incomplete fields remain nullable and are not treated as zero, exempt, or approved. `isCompleteForCalculation()` is a readiness predicate only; the statutory calculation engine must also check policy configuration and other period inputs before calculating.

## Validation

Automated tests cover period overlap and boundaries, contract-date containment, currency and salary-basis validation, allowance shape, verification metadata, legacy field preservation, and deletion protection. Synthetic fixture values are test data only.
