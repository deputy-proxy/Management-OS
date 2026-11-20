# MCP approval and execution audit controls

## Status and boundary

Issue #532 implements the approval and audit infrastructure required before controlled MCP writes. The MCP capability registry and business permission matrix remain empty. No business write tool is registered or enabled by this issue.

Future write tools must use the approval service and an existing domain service. A tool must not mutate domain data directly from its initial MCP request. This infrastructure is not a substitute for domain-specific authorization, record scoping, field allowlists, or service-level validation.

## Persistence

Two dedicated tables separate operation payloads from the audit trail:

- `mcp_approvals` stores the AI principal, optional trusted human initiator, OAuth client, tool and permission-matrix version, target, request ID, idempotency key, canonical payload hash, encrypted arguments, encrypted human-readable preview, lifecycle state, decision rationale, encrypted result, and expiry/review/execution timestamps.
- `mcp_execution_audits` stores the AI principal, OAuth client, tool/version, target, request ID, idempotency key, payload hash, approval reference, human initiator/approver where known, event type, status, safe result/error code, timestamps, and a domain-event reference where available.

The audit table deliberately has no argument or result payload column. Raw arguments, encrypted approval arguments, result bodies, tokens, credentials, and exception messages are not copied into audit events. The approval model encrypts its argument, preview, and result arrays at rest using Laravel's application encryption key. Protect `APP_KEY` and backups accordingly.

Approval and audit identity/payload fields are immutable through their Eloquent models. Application-level deletion is denied. Foreign keys restrict deletion of principals and human reviewers while retained records refer to them. The approval-to-execution pointer is intentionally nullable and does not create a cyclic foreign key.

## Approval lifecycle

The current states are:

| State | Meaning |
| --- | --- |
| `pending` | Validated request is awaiting an independent Management-OS human reviewer |
| `approved` | A permitted human reviewer approved the exact payload |
| `rejected` | A permitted human reviewer rejected the request |
| `expired` | The approval deadline passed before review or execution |
| `cancelled` | The requesting AI principal cancelled a pending or approved request |
| `executing` | A single worker claimed the approved operation |
| `executed` | The domain callback returned a confirmed result and it was persisted |
| `execution_blocked` | Authorization was revoked at the final pre-execution check; the callback was not invoked |
| `execution_uncertain` | Execution returned no confirmed outcome or was interrupted; automatic retry is prohibited |

Pending and approved requests expire after 15 minutes by default. Configure the duration with `MCP_APPROVAL_TTL_MINUTES` (bounded to 1–1,440 minutes). The maximum encoded argument/result size is 64 KiB by default (bounded between 1 KiB and 1 MiB); configure it with `MCP_APPROVAL_MAX_PAYLOAD_BYTES`.

A request requires a non-empty human-readable preview of up to 20 short scalar fields. Field names that indicate credentials, contact details, banking, identity or medical data are rejected. Preview values are encrypted at rest and only the preview, not the raw arguments, is shown in the Management-OS list.

The human reviewer must have the explicit `mcp_approval.approve` permission and must be distinct from the AI principal and, when known, the human initiator. The AI principal is denied all approval UI permissions even if a role is misconfigured. Review actions require a concise rationale. Approval and rejection are separate audited events.

The OAuth consent flow currently does not persist the human who initiated the ChatGPT connection as an operation initiator. `human_initiator_id` therefore remains null unless a trusted server-side integration sets the `mcp_human_initiator_id` request attribute. Never accept this identity from MCP arguments.

## Payload binding, idempotency and execution

The SHA-256 payload hash covers canonicalized arguments, preview, tool name, permission-matrix version, and target type/ID. Associative object keys are sorted; list order is preserved. Approval fails if the stored payload no longer hashes to the original value or if the permission-matrix version changed.

The approval request requires the OAuth client ID extracted from the validated Passport JWT. Execution and cancellation are bound to that same client ID; a different ChatGPT OAuth client cannot reuse or execute another client’s approval. The client identifier is also included in the invocation and approval audit records.

The idempotency key is unique per AI principal and OAuth client:

- Same principal + same client + same key + same canonical payload returns the original approval and, after execution, its stored result.
- Same principal + same key + different client or different payload is rejected.
- A database uniqueness constraint resolves simultaneous duplicate submissions.

Approval is single-use. Execution claims the approval row under a database lock and moves it to `executing` before the callback can run. A second worker cannot claim an already executing operation. The service re-checks the current AI principal, OAuth scopes, domain permissions, payload integrity, expiry, and distinct human approver. It performs a second authorization check immediately before invoking the domain callback.

A future write tool must declare `operation_type => write` and `requires_approval => true`. Generic `addAuthorizedTool()` registration rejects writes; `addApprovalRequestTool()` can only create a pending request and never invokes a domain callback. A future approved-execution tool must dispatch through `McpApprovalService::executeApproved()`.

A future tool callback must call the existing application/domain service. It must not implement parallel business logic or write directly to Eloquent models to bypass existing policy and lifecycle checks.

If authorization is revoked at the final check, the operation becomes `execution_blocked` and the domain callback is not invoked. If the callback throws, returns an invalid/oversized result, or the process stops before a confirmed completion is stored, the operation becomes `execution_uncertain`. The service never retries an uncertain operation automatically. Reconcile the domain state and its domain event before taking any manual recovery action.

The scheduled `mcp:expire-approvals` command runs every minute. It expires pending/approved requests and marks stale `executing` claims uncertain after five minutes by default (`MCP_EXECUTION_STALE_MINUTES`). The stale threshold must remain longer than the deployed maximum synchronous tool execution time.

## Management-OS access

The authenticated Filament panel exposes two read-only resources:

- **MCP approvals** at `/admin/mcp-approvals`
  - `mcp_approval.index`: view approval requests and safe previews.
  - `mcp_approval.approve`: approve or reject a pending request with a required rationale.
- **MCP execution audit** at `/admin/mcp-execution-audits`
  - `mcp_execution_audit.index`: view execution and approval audit events.

Grant these permissions only to named staff roles responsible for reviewing MCP activity. The AI Assistant role must not receive them. Resources do not provide create, edit, delete, bulk-delete, or export actions. The service repeats authorization checks when an action is submitted; hiding a UI action is not the security boundary.

## Audit retention and incident review

MCP audit records are retained as operational accountability records and cannot be deleted through the application. No MCP-specific retention duration has been approved by the policy owner/legal reviewer. Therefore no automatic MCP audit pruning or deletion is scheduled. Set a documented retention period only after the owner/legal review; do not infer that the existing Spatie Activity Log's 365-day cleanup setting applies to these dedicated MCP tables.

Access to approval records and execution audit records is permission-gated as described above. Review access should be limited to designated staff and reviewed periodically. Do not export or copy decrypted approval arguments/results into tickets or chat transcripts.

For an MCP incident:

1. Contain access first. Remove the AI principal's mapped domain permission or revoke its tokens with `php artisan mcp:revoke-principal-tokens` where token compromise is suspected.
2. Search the execution audit by request ID, approval ID, OAuth client ID, AI principal, tool/version, target, idempotency key, and time window.
3. Verify the payload hash, reviewer identity, rationale, approval status, and domain-event reference. Retrieve decrypted arguments only from the protected approval UI/service when strictly necessary.
4. For `execution_uncertain`, inspect the domain record and its own event/audit history before any recovery. Never retry simply because the MCP caller did not receive a response.
5. Record the incident decision and any manual reconciliation in the relevant domain incident/change record. Preserve the MCP approval and audit rows.
6. Restore permissions or reauthorize the connector only after containment and review are complete.

## Validation

The feature tests cover encrypted payload persistence, canonical hash stability, idempotency collisions, payload tampering, independent human review, AI self-approval denial, known initiator separation, rejection, cancellation, expiry, permission revocation immediately before execution, concurrent claims, uncertain outcomes, stale recovery, immutable audit history, and the Filament review pages. The MCP authorization tests also assert success and denied invocation audit records.
