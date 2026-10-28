# Worker arrangements: application inventory

**Status:** Implemented application workflow for explicitly recording worker-engagement classifications. Records remain unclassified until an authorized operator enters a supported classification.

## Data model

The `worker_engagements` table stores an explicit engagement type (`employee`, `pfa_contractor`, `company_supplier`, `other`, or `unknown`), lifecycle status, optional effective dates, an optional source reference, and links to existing employee/vendor and contract records.

The record links to at most one of `Employee` or `Vendor`. Employee engagements must link to an employee; PFA and company/supplier engagements must link to a vendor. A linked contract must belong to the selected employee/vendor. Unknown arrangements may remain unlinked. Dates are validated, records are soft-deleted, activity changes are logged, and foreign keys restrict hard deletion of linked source records.

## Application behavior

- Filament provides list, create, view, and edit screens under Human Resources, with filters for engagement type/status and a badge for unresolved records.
- `WorkerEngagement::current($date)` returns active records whose effective dates include the requested date.
- Classification is never inferred from a login, vendor record, invoice, transaction, or payslip.
- Employee payslip and vendor invoice/payment workflows remain separate.
- `source_reference` is intended for a non-sensitive document identifier or internal reference, not personal identifiers or document contents.
- Feature tests cover unknown arrangements, employee/vendor links, contract ownership, date validation, current-state filtering, and hard-delete protection.

## Boundaries

The model records operational classification; it does not assert that a classification is legally correct or automate statutory payroll calculations. No actual worker roster is seeded or inferred. Existing records are not backfilled because the available schema cannot safely establish their engagement type or effective dates. Unknown values remain explicit until data is entered through the application.
