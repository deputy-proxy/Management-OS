# Synthetic worker-arrangement fixture

**Status:** example-only; not a real worker roster, owner-reviewed classification, or production seed data.

The machine-readable examples live in [`tests/Fixtures/Payroll/worker-arrangements-synthetic.json`](../tests/Fixtures/Payroll/worker-arrangements-synthetic.json). They complement the [worker-arrangements inventory](worker-arrangements-inventory.md), which describes what repository code can represent and records that actual worker counts and classifications remain unknown without owner-provided source records.

## Included scenarios

| Synthetic scenario | Intended workflow boundary | Required evidence before real classification |
| --- | --- | --- |
| Employee with an employee contract | Existing activity-based employee payslip workflow; no inferred statutory net pay | Authoritative HR roster, signed employment contract, effective-dated amendments |
| Legal-entity supplier | Vendor contract, invoice and payment evidence remain separate from employee payslips | Vendor master record, signed supplier contract, invoice and tax-identity evidence |
| Individual/PFA candidate | Classification and automated processing remain blocked | Contracting-party identity, signed contract, qualified accounting classification |
| Other or not-yet-modeled arrangement | Keep an explicit unresolved case; do not infer absence from missing schema | Owner description, underlying contract/legal basis, qualified review |

The fixture's `synthetic` metadata and `example-only-not-owner-reviewed` status are deliberate. Tests enforce that employee and supplier examples use separate workflow labels and that unresolved individual/PFA and other arrangements are not routed automatically into payroll or contractor-invoice handling.

## Limits and next steps

- The examples do not establish which arrangements the business actually uses.
- No statutory tax, contribution, withholding, VAT, or net-pay calculations are encoded.
- Before production classification, HR/Finance must provide a dated roster reconciled to employee/vendor/contract/invoice records and cite the underlying evidence and effective dates.
- A qualified Romanian payroll/accounting reviewer must resolve ambiguous arrangements. Unknowns remain unknown until evidence is reviewed.
- The fixture is a proposed example contract, not an approved schema or permission to migrate production data.
