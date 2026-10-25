# Customer Leads

Customer Leads are prospective-customer records used by the customer management pipeline. They are separate from Customers until the dedicated conversion workflow succeeds. Public enquiries enter the pipeline with status `new` and a recorded privacy-consent timestamp.

## Data fields and relationships

- **Identity:** `first_name`, optional `middle_name`, `last_name`, and derived/display `full_name`.
- **Contact:** `email` and `phone`. The Filament form validates email format and rejects another non-deleted lead with the same email; deleted leads do not block reuse.
- **Attribution and interest:** `source`, `topic_interest`, and `product_interest`.
- **Pipeline:** `status`, `last_contacted_at`, plus timestamps and soft deletion.
- **Privacy:** `privacy_consented_at` records consent for public enquiries.
- **Conversion trace:** `converted_customer_id` and `converted_at` link a converted lead to its Customer. `customerContract()` links the unique contract created from the lead. `customerInteractions()` exposes interactions associated with its converted Customer; unconverted leads have no customer interactions yet.
- **Contract delivery:** `contract_document`, `contract_send_status`, `contract_sent_at`, and `contract_send_error` track delivery of an existing PDF, as described in [Customer Lead contract delivery](customer-lead-contract-delivery.md).
- **Operational history:** leads support comments, tasks, and activity logging.

## Lifecycle

Statuses are stored lowercase and restricted to:

| Status | Meaning | Allowed next steps |
| --- | --- | --- |
| `new` | Enquiry captured, not yet qualified | `contacted`, `qualified`, `lost` |
| `contacted` | Initial follow-up recorded | `qualified`, `proposal`, `lost` |
| `qualified` | Lead appears suitable | `contacted`, `proposal`, `lost` |
| `proposal` | A proposal or next-step offer is in progress | `qualified`, `converted`, `lost` |
| `converted` | Conversion workflow created/linked a customer and signed contract | Terminal; cannot return to the lead pipeline |
| `lost` | Lead is not proceeding | `new` if re-opened; conversion requires reopening first |

The Filament form exposes the active pipeline statuses and does not allow staff to set `converted` directly. Conversion is owned by issue #16's `ConvertCustomerLead` action and requires a linked customer, conversion timestamp, signed contract, and explicit confirmation. Contract sending remains owned by issue #25's `SendCustomerLeadContract` action. This resource issue does not duplicate either workflow.

The model normalizes status case, validates the supported status set, and enforces the transition graph in the table above. A lead cannot be marked converted without conversion tracking, and an already converted lead cannot be reverted. Legacy unrecognized status values are normalized to `new` by migration `2026_10_18_140000_normalize_customer_lead_statuses`.

## Resource UX and access control

The Customer Leads resource provides searchable name, email, phone, source, interest, and status fields; a status filter and soft-delete filter; a view page; and status badges. Existing HexaLite gates continue to control list, create, update, and delete operations. Conversion and contract delivery actions retain their own permission checks and domain actions. Hiding an action in the UI is not a replacement for those server-side checks.

Duplicate email validation is scoped to non-deleted leads and ignores the current record when editing. The public enquiry endpoint continues to record the submitted lead with status `new`; it does not convert a lead or send a contract.

## Lifecycle transition history

New lead records write an initial history entry with a null `from_status` and the stored initial status as `to_status`. Each subsequent Eloquent status change writes a separate `customer_lead_status_histories` row containing the previous status, new status, timestamp, and authenticated actor when available. Non-status edits do not create lifecycle entries. The history records the application's observed status changes; it does not infer when a stage began before this migration was deployed, and existing leads are not backfilled with invented history. Direct SQL or bulk updates that bypass model events do not emit these entries, so lifecycle reporting must treat missing history as unknown rather than as proof that no transition occurred.
