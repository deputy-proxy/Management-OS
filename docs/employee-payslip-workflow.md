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

New payslips start with `status=Open` and `approval_status=draft`. The guarded approval workflow is `draft -> in_review -> approved -> closed`; the legacy `status` field moves from `Open` to `Closed` only when the lifecycle service closes an approved period. Submission requires the latest run for the period to be successfully calculated and no unresolved blocking payroll exceptions. The review snapshot contains the payslip baseline and legacy breakdown, source-linked items, all calculation/input snapshots, policy and calculation versions, result hashes, and the calculation initiator. Its canonical SHA-256 hash is retained.

Approval requires a different actor from both the calculation initiator and reviewer. It re-reads the period under a row lock, verifies that the review hash still matches current records, and stores a second immutable approved snapshot/hash with actor and timestamp. Close again checks the current hash and blocking exceptions under a row lock, records the closer and timestamp, advances the lifecycle version, and appends an immutable audit event. Duplicate/stale transitions fail rather than generating a second approval or close. UI visibility is backed by service-layer permissions (`payroll_period.review`, `.approve`, `.close`, and `.correct`).

Reviewed, approved, and closed periods cannot be edited or recalculated. Source-linked items and historical approved snapshots are immutable. Corrections to an item in the current draft/open period can be recorded on that same payslip. Once the original period is closed, the correction must be a signed adjustment on a later open period for the same employee. Both paths reference the original item, require a reason, and append a lifecycle event. The original item and approved snapshot are never rewritten. Legacy closed/paid rows are tagged `legacy_closed`/`legacy_paid` so their historical status is preserved without inventing approval evidence.

New transitions to `Paid` are deliberately disabled until verified payment evidence is integrated. A payment date alone is not evidence and cannot mark a payslip Paid. Existing historical Paid rows remain readable and locked. `due_on` remains optional because the repository does not define a payment deadline.

## Migration and operations

The payroll schema migration normalizes existing month values before adding the unique index and fails with a reconciliation message if existing duplicates would collide. Existing integer amounts are preserved as decimal values and copied to `manual_amount`. The line-item migration refuses rollback when it would discard persisted audit items; the Test Simulation migration similarly refuses rollback while simulation records exist. Export/reconcile those records before a deliberate schema rollback.

Run `php artisan employee-payslips:generate --month=YYYY-MM` to backfill or recover a month. Re-running is safe. Verify the payslip count, line-item source IDs, snapshots, and total amount before closing a period.

## Representation contract and retirement decision

The two representations are intentionally not interchangeable:

| Representation | Meaning and owner | Current behavior | Retirement constraint |
| --- | --- | --- | --- |
| `employee_pay_slips.items` JSON | Legacy/manual payroll and statutory breakdown, edited through the `Legacy Payroll Breakdown` Builder in `EmployeePaySlipForm` and displayed by `EmployeePaySlipInfolist` | Preserved as stored JSON; editable only while the payslip is Open. No automatic conversion to source-linked rows occurs. | Historical blocks do not consistently contain a verified source record, source date, rate, or formula. Do not infer these fields or split the JSON into normalized rows without an authoritative mapping. |
| `employee_pay_slips.manual_amount` | Existing manually calculated baseline; it was backfilled from the previous `amount` column by the monthly workflow migration | The baseline is combined with normalized line amounts to produce the displayed `amount`. The JSON breakdown is not independently re-summed into the total, which would risk double-counting the old baseline. | Preserve the original baseline and the legacy JSON separately; do not replace one with the other. |
| `employee_pay_slip_items` | Source-linked activity and adjustment ledger, owned by `EmployeePaySlip::lineItems()` | Stores source identity, date, quantity, unit/rate, amount, currency, and immutable calculation snapshot. `EmployeePaySlipService` creates these rows and recalculates `amount` as `manual_amount + sum(line item amounts)`. | Rows are immutable and are not a lossless mirror of the JSON breakdown. Keep source IDs, snapshots, and adjustment links. The migration refuses rollback while rows exist. |
| `employee_pay_slips.amount` | Aggregate displayed/paid total | New/updated totals are maintained from the manual baseline plus normalized source lines; the migration copies the old total into `manual_amount` before the new aggregation behavior is used. | Closed and paid values must remain stable. Corrections are signed adjustment rows on a permitted open period, not edits to historical items. |

### Verified reads and writes

- `EmployeePaySlip` casts `items` to an array and treats it as a fillable field; its save guard allows financial edits only while the slip is draft/Open, freezes the baseline, legacy JSON, aggregate amount, and due date after review begins, and prevents new Paid transitions without payment evidence.
- `EmployeePaySlipForm` exposes the legacy JSON Builder and manual baseline only while Open. `EmployeePaySlipInfolist` displays the JSON breakdown separately from normalized source-linked rows.
- `EmployeePaySlipService` writes normalized rows through `EmployeePaySlipItem`; item creation and recalculation are draft/Open-only, and the source rows cannot be updated or deleted. The service does not read, rewrite, or convert the legacy JSON. `EmployeePaySlip::recalculateAmount()` sums only `manual_amount` and normalized row amounts.
- `EmployeePaySlipSchemaMigrationTest` verifies the existing total is copied to `manual_amount`, the legacy JSON and payment dates are preserved, and duplicate employee/month rows stop migration pending reconciliation.
- `EmployeePaySlipWorkflowTest` verifies the baseline plus source-line total, JSON preservation, legacy Closed/Paid locking, immutable source rows, and signed adjustments. `PayrollPeriodLifecycleTest` verifies the separate review/approval/close state machine, actor separation, snapshot immutability, correction/reversal behavior, and transaction rollback.

### Retirement decision

Keep the coexistence until a payroll-authoritative specification defines a lossless mapping from each legacy JSON block to normalized rows and verifies how those rows affect the manual baseline and paid total. The current data does not establish reliable source IDs, rates, or statutory calculation semantics for every legacy block. Migrating the JSON now would require inventing missing facts or risk double-counting. No JSON data is deleted or rewritten by the current workflow migration, and no tax/statutory calculations are inferred.

Any future single-representation migration must first export and reconcile existing slips, preserve the original JSON and historical snapshots, compare per-slip totals before and after, verify Open/Closed/Paid immutability, and provide a tested recovery plan. The line-item migration's rollback guard must remain in place unless an explicit data-retention and recovery strategy replaces it.
