# Internal payroll period exports

## Supported reports

Exports are internal management records, not statutory submissions. The download service supports three report types:

- **Internal period summary:** employee/period identifiers, selected calculation version, policy ID/version, currency, gross, deductions, net, rounding metadata, result hash, approval status and approval/close metadata.
- **Calculation line-item detail:** the selected calculation result's immutable line items, including item type/code, label, calculation method, source parameter, input/rate, amount, currency, rounding metadata, and calculation snapshot.
- **Calculation exception list:** immutable detection evidence for exceptions linked to the selected run and detected no later than period approval. The export deliberately omits mutable resolution state; the live exception queue and append-only event history remain the source for current resolution status.

CSV and JSON are supported. CSV is UTF-8 with a BOM and CRLF row endings; formula-like text cells are escaped. JSON is UTF-8 with unescaped Unicode. Output is deterministic for the same approved period, calculation run/version, report type and format. The filename and metadata carry the period, run ID, calculation version and approved snapshot hash.

## Eligibility and audit

A report can be exported only from an `approved` or `closed` payslip with an immutable approved snapshot. The selected calculation run must be present in that snapshot and match its calculation version, result status and result hash. Summary and line-item reports require a calculated result, a versioned policy reference, complete totals and a valid currency code. Line-item rows must have required fields and consistent currency. Exception reports can also be generated for blocked historical runs included in the approved snapshot.

The `payroll_period.export` permission is checked by the Filament action and again in the export service. Downloads are streamed with private/no-store headers; no artifact is stored in public storage. Each export writes an immutable `payroll_export_events` record with actor, payslip, run, report type, format, row count, approved snapshot hash and SHA-256 content hash. The audit migration refuses rollback after an export event exists.

Statutory tax/payroll submissions, legal declarations, external filing formats, and claims of statutory compliance are explicitly unsupported. Missing policy/result fields, unsupported formats/types, invalid UTF-8 and unapproved/unlocked periods are rejected rather than silently defaulted. Exporting does not file or transmit anything to an authority or payment provider.
