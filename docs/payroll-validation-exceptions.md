# Payroll validation and exceptions

Payroll calculation runs create a durable validation exception for each detected blocking condition and each non-blocking warning. Exceptions identify a stable code, severity, status, payroll period, related employee/contract/run, safe explanatory text, and remediation guidance. Raw salary values and other sensitive input data are not copied into exception messages.

Blocking exceptions include missing or unverified payroll inputs, effective-date gaps or overlaps, unsupported contract/rule combinations, incomplete policy configuration, missing source references, locked payslips, and inconsistent calculated totals. A second calculation snapshot for the same contract and period is recorded as a warning because it may represent an intentional correction; the earlier snapshot is retained.

Authorized users can resolve an open exception with a required note. Detection and resolution are recorded as append-only events. Exception evidence cannot be edited or deleted. A payslip cannot move from Open to Closed while an open blocking exception is linked to it. Warnings do not block closure.

These checks validate application data and configured arithmetic only. They do not establish statutory correctness, replace specialist review, or require production payroll reconciliation. Unsupported statutory or policy treatments remain explicit blockers rather than inferred calculations.

The Filament Payroll Exceptions resource is permission-controlled by `payroll_exception.index` and `payroll_exception.resolve`. Resolution is also authorized in the service layer.
