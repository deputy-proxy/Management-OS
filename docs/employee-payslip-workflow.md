# Employee payslip workflow

## Periods and eligibility

A payslip period is a calendar month. `employee_pay_slips.month` is normalized to the first day of that month, and a unique index on `(employee_id, month)` prevents duplicates. The scheduled command `employee-payslips:generate` runs on day 1 at 00:05 in the application timezone. It is idempotent and can be run manually with `--month=YYYY-MM` for recovery or backfill.

An eligible employee is a non-deleted employee with at least one non-deleted `EmployeeContract` overlapping any day of the month. If contracts overlap, the rate lookup uses the contract with the latest `start_date`, then highest ID, that is active on the activity date. The monthly equipment clause uses the latest-starting contract that overlaps the period. Existing deleted payslips are never silently recreated; restore or reconcile them first.

## Line-item storage and amounts

The existing `EmployeePaySlip.items` JSON remains the legacy payroll/statutory breakdown. New source-linked activity lines are normalized in `employee_pay_slip_items` so every line has a source type/ID, date, quantity, unit, rate, amount, currency, and immutable `calculation_snapshot`.

`manual_amount` is the pre-existing manually calculated payroll baseline. The migration backfills it from each existing payslip's `amount`. The displayed `amount` is now `manual_amount + sum(line item amounts)`. Newly generated slips start with a zero baseline; the workflow does not automatically calculate gross salary, CAS, CASS, income tax, or other statutory payroll amounts because the current repository does not define verified proration or tax-calculation rules. Payroll staff continue to maintain the existing JSON breakdown and baseline, while source-linked activity amounts are added automatically.

Activity lines use `EmployeeContract.hourly_rate` in RON. Session hours are calculated from `starts_at` to `ends_at`; session assistance uses the linked session duration; test simulations use their explicitly recorded hours. Hours and money are rounded half-up to two decimal places. The contract ID, contract type, rate, source identifiers, formula, and source timestamps are captured in the snapshot at the time the line is added. Changing a source record or contract later does not rewrite a historical line.

Approved holiday ranges contribute weekdays only (Monday through Friday). `hours_per_day` is explicit on the holiday record and defaults to 8 hours for existing/new records; each covered weekday uses the contract rate active on that date. The snapshot records each covered date and rate. A monthly own-equipment reimbursement is added when the selected contract contains the canonical clause `use_own_equipment_reimbursement` with a numeric amount. The migration normalizes legacy Builder block types such as `UseOwnEquipmentReimbursment` and `use_own_equipment_reimbursment`; the model also reads legacy flat JSON keys for compatibility. New contracts should use the corrected key.

## Sources and duplicate prevention

The `Add to Pay Slip` action is available on non-cancelled Sessions, completed (`complete`/`completed`) Session Assistance records, and completed Test Simulations. It resolves the employee and calendar month from the source, requires an active contract on the activity date, creates the monthly slip if needed, and adds the source line in a transaction. The unique `(employee_pay_slip_id, source_type, source_id)` index prevents per-slip duplicates, and the unique `source_key` prevents the same one-off session, assistance, or simulation from being paid to a second employee or period. Recurring contract reimbursements, holidays, and adjustments use a period-scoped source key. Cancelled sessions cannot be added. Only completed test simulations can be added.

`TestSimulation` is a distinct scheduled activity record, not the content `Test` model. It records the teacher, optional test/group, title, date, hours, status, and notes. This supplies the previously missing source record for mock exams/simulations.

## Status, locking, and corrections

Payslips start `Open`. Allowed transitions are `Open -> Closed -> Paid`; `Paid` requires `paid_on`. Closed and paid payslips cannot have their baseline or legacy breakdown recalculated, and source-linked items cannot be updated or deleted. `due_on` is optional because the repository does not define a payment deadline.

If a source record changes or is cancelled after a line was added, the saved snapshot remains unchanged. Do not edit or delete the line to conceal the historical state. While the original payslip is still Open, record a signed adjustment on that same payslip. Once it is Closed or Paid, record the correction on a later Open payslip. Reference the original line and provide a reason: positive for an underpayment, negative for an overpayment. Paid periods remain locked. A source correction is a new audit event, not a rewrite of the original calculation.

## Migration and operations

The payroll schema migration normalizes existing month values before adding the unique index and fails with a reconciliation message if existing duplicates would collide. Existing integer amounts are preserved as decimal values and copied to `manual_amount`. The line-item migration refuses rollback when it would discard persisted audit items; the Test Simulation migration similarly refuses rollback while simulation records exist. Export/reconcile those records before a deliberate schema rollback.

Run `php artisan employee-payslips:generate --month=YYYY-MM` to backfill or recover a month. Re-running is safe. Verify the payslip count, line-item source IDs, snapshots, and total amount before closing a period.
