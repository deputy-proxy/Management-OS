# PFA contractor invoice lifecycle

## Scope and separation

PFA contractor invoices use the existing `Vendor`, `VendorContract`, `VendorInvoice`, `Transaction`, and `PaymentAllocation` structures where their meanings match. An invoice participating in this lifecycle must link to an active `WorkerEngagement` explicitly classified as `pfa_contractor`, with the same vendor and contract. The workflow does not create or use employee payslips and does not infer a worker's legal classification from names, invoices, payments, or other records.

## States and transitions

- `unresolved` → `submitted`: the invoice has an active PFA engagement, matching vendor/contract, invoice number, source identifier and document reference, and no duplicate invoice number for that vendor.
- `submitted` → `approved` or `rejected`: an authorized user records a review decision and reason. Rejected invoices can be submitted again after correction of the supporting data.
- `approved` → `payable`: an authorized user records that the approved invoice is payable and optionally records its payable date.
- `approved` or `payable` → `reversed`: an authorized user supplies a reason. Confirmed payment allocations must first be reversed.
- `approved` or `payable` → `corrected`: create a linked correction invoice with an explicit reason. Confirmed allocations must first be reversed. The original is retained and locked; the correction starts unresolved and must pass review separately.

Lifecycle events record the actor, transition, timestamp, reason and relevant references. Reviewed invoice identity and amount fields are locked; corrections do not overwrite the original. Records with payment, correction or lifecycle history cannot be deleted through the model.

## Payments and tax

Existing payment allocations remain the payment-link mechanism. A proposed allocation is not settlement; only confirmed allocations count in the allocation summary. Because legacy invoice amount semantics are not yet fully resolved, allocation totals do not automatically mark an invoice paid or calculate an outstanding balance. Currency mismatches remain review states; foreign-exchange allocation is not supported by this workflow.

Tax treatment defaults to `unsupported`. A reviewed, versioned rule reference may be selected only when present in `config/contractor-tax.php` and effective for the invoice period. The registry is empty by default. Recording a rule key/version does not calculate or hard-code withholding, VAT, income-tax or contribution rates, and does not assert statutory compliance. The workflow records operational review and payable state, not legal classification approval or statutory compliance.

## Permissions and tests

Lifecycle transitions use the existing `vendor_invoice.update` authorization gate in the service layer. Tests use synthetic records and cover engagement requirements, duplicate invoices, transitions, rejection reasons, immutable approvals, corrections, reversals and event history. No actual counterparty data or tax calculations belong in fixtures.
