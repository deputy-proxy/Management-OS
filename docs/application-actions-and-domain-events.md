# Application actions and domain events

Management-OS remains a Laravel modular monolith. Use application actions/services for reusable business workflows; do not put business invariants in Filament components, controllers, queue jobs, or MCP adapters.

## Application-action convention

- Give an action one business purpose and a descriptive name (`ConvertCustomerLead`).
- Accept typed domain models and a documented, validated input shape. Keep authorization at the trusted entry point and ensure every entry point enforces the same authorization policy.
- Make the action own the transaction boundary when multiple writes must succeed together. Lock records when the invariant depends on current state.
- Return a useful domain result or throw a controlled validation/domain exception. Do not return UI-specific notifications from an action.
- Keep retries safe through database uniqueness/invariants and idempotent operation keys where applicable.
- Keep Filament actions thin: collect/validate input, call the shared action/service, then present the result.

## Domain-event convention

Emit an explicit event for a meaningful successful business transition, not for every model `created`/`updated`/`deleted` event. Event payloads should contain stable identifiers and avoid unnecessary personal or sensitive data. Events implementing Laravel's `ShouldDispatchAfterCommit` are dispatched only after the enclosing transaction commits; rolled-back work must not trigger listeners or external effects.

Listeners and jobs should be safe to retry. Use a durable unique event/delivery key for externally visible effects, and use `ShouldQueueAfterCommit`/after-commit dispatch for queued work. Do not assume a queue's current synchronous configuration provides production delivery guarantees. For effects that require guaranteed delivery across process crashes, use a transactional outbox in a separately scoped change rather than claiming ordinary after-commit dispatch is durable.

## Reference implementation

`ConvertCustomerLead` is the shared workflow for lead conversion from the Filament table action. It owns locking, duplicate/identity checks and the customer/contract/lead transaction. On success it emits `CustomerLeadConverted` with lead, customer and contract IDs. The event implements `ShouldDispatchAfterCommit`, so conversion listeners cannot run for a rolled-back conversion. There is intentionally no side-effect listener yet; consumers should be added only when a concrete business requirement and idempotency strategy are defined.

Provider responsibilities are separated: `DomainModelServiceProvider` registers morph aliases and domain observers, `ModelNotificationServiceProvider` wires model-change notifications, and `CommentNotificationServiceProvider` handles comment notifications. Preserve the morph map values and observer behavior when extending these registrars.
