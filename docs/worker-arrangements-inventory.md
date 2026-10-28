# Worker arrangements inventory

The application implementation is documented in [Worker arrangements: application inventory](worker-arrangements-inventory-v1.md).

The current workflow stores explicit engagement classifications and effective dates in `worker_engagements`, links records to existing employee/vendor contracts, preserves unresolved arrangements, and provides current/history/unresolved views in Filament. It does not infer legal classification from a user account, vendor, invoice, transaction, or payslip, and it does not enable statutory payroll calculations.

The synthetic examples in [worker-arrangements-synthetic-fixtures.md](worker-arrangements-synthetic-fixtures.md) remain test-only examples. They are not production records or a source for classifying actual counterparties.
