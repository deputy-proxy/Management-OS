# Customer Lead contract delivery

## Source and delivery mechanism

The first version sends an existing contract PDF attached to a Customer Lead. Staff upload the PDF in the lead's Contract Delivery section; files are stored on Laravel's private `local` disk under `customer-lead-contracts/`, outside the public storage symlink. The recipient is the lead's existing email address. No PDF generator, contract template engine, or e-signature provider is configured in the repository, so this implementation does not invent a template or claim electronic signing support.

The email uses the `CustomerLeadContractMail` view and attaches the uploaded file as `contract.pdf`. Delivery uses the application's configured Laravel mailer (`MAIL_MAILER` and its mailer-specific configuration). In production, configure a real transport and queue worker before enabling the workflow for staff. The default `log` mailer is useful for development but does not deliver email to an external recipient.

## State transitions and duplicate protection

`contract_send_status` starts as `not_sent`. A send request validates the recipient and confirms the PDF exists before changing state. It transitions to `queued` before dispatching the job. The job sets `sent` and `contract_sent_at` only after `Mail::send()` returns successfully. A failed job or synchronous dispatch failure sets `failed`, records a truncated error in `contract_send_error`, and leaves `contract_sent_at` null. Failed sends may be retried by staff after correcting the issue. `queued` and `sent` states block a second action to prevent accidental duplicate sends.

A successful send state means the configured mail transport accepted the message; it does not guarantee final inbox delivery. Transport/provider delivery events are not integrated. Queued delivery requires a functioning queue worker, and asynchronous failures are recorded by the job's failure callback.

## Permissions and auditability

The action is available only to users with `customer_lead.update` permission and is hidden for soft-deleted leads or leads whose send is already queued/sent. The confirmation modal previews the recipient and selected document. The lead stores the PDF path, state, successful-send timestamp, and most recent error for operational review. The existing model activity log continues to record model changes.