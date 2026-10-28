# Payroll policy configuration

`PayrollPolicy` stores versioned, effective-dated payroll configuration. It is a configuration registry, not a statutory calculation engine and not a source of legally verified Romanian tax rates.

## Lifecycle

- **Draft** versions are editable. A draft can be activated only through `PayrollPolicyService::activate()`.
- **Active** versions are immutable and are selected by policy key plus the requested effective date.
- **Retired** versions remain in the database for audit/history and are not selected for new calculations.
- Create a new draft version to change a policy. Existing versions and their parameters are not rewritten.
- Two active versions with the same policy key may not have overlapping effective date ranges.

The `payroll_policy.*` permissions control access to listing, creating, editing, activating, and retiring versions. The Filament UI uses these gates, while lifecycle and validation rules are enforced in the model/service as well.

## Configuration fields

- `policy_key`: stable lowercase identifier such as `ro.employee.payroll`.
- `version`: positive integer unique within the policy key.
- `effective_from` / `effective_until`: inclusive effective date range; a null end date is open-ended.
- `parameters`: JSON object of configured values, with nested paths supported for gap detection.
- `required_parameters`: list of parameter paths that must be present before a dependent calculation may run.
- `calculation_order`: ordered identifiers for calculation steps. Each identifier must refer to a configured top-level parameter key; identifiers are data only and are not executable expressions.
- `rounding_mode` and `rounding_precision`: data-only settings; precision is from 0 to 6 and mode is one of `half_up`, `half_down`, `half_even`, or `half_odd`.

`configurationGaps()` returns required paths that are absent, null, or empty and reports a gap when rounding mode or precision is not configured. `isCalculable()` is true only for an active policy with no required parameter or rounding gaps. This is a readiness check only; it does not calculate payroll or validate that configured values are legally correct. Missing configuration must remain missing, never be replaced with invented defaults.

## Integration contract

Use `PayrollPolicyService::resolve($key, $date)` to find the active version effective on a date. Use `missingParameters($key, $date)` to retrieve configuration gaps. A missing policy is represented by a `policy:<key>@<date>` gap. Calculation services must block affected calculations when the policy or required parameters are missing and must snapshot the policy key/version with any result.

Employee payslip activity calculations remain unchanged. This registry does not modify historical payslip JSON, activity line snapshots, amounts, or period state. PFA/vendor invoice workflows remain separate from employee payroll.

## Tests

`tests/Feature/PayrollPolicyTest.php` covers version persistence, effective-date resolution, activation, overlapping ranges, missing parameter detection, immutability, retirement, next-version creation, and invalid configuration.
