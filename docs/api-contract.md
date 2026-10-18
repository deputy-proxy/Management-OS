# Management-OS API contract and integration boundary

## Consumer inventory and scope

The repository currently establishes one API use case: an authenticated user reads their own profile. No supported external integration consumer, owner, or additional data requirement is documented in the repository. Do not infer that a consumer exists merely because a resource exists in Eloquent or Filament.

| Endpoint | Consumer / use case | Data scope | Authentication and permission | Owner |
|---|---|---|---|---|
| `GET /api/v1/user` | Authenticated account client reads its own profile | Only the authenticated user's `id`, `name`, and `email` | Sanctum authentication plus `profile:read` token ability | Management-OS application team; named individual owner and external consumers remain to be assigned |
| `GET /api/user` | Compatibility for existing clients of the pre-versioned endpoint | Same minimal self-profile fields | Sanctum authentication; retained compatibility route does not bypass authentication | Management-OS application team; inventory existing clients before deprecation |

No generic CRUD endpoints are exposed. Employee data, payroll, finance, students, customer records, assessment data, and write/command operations are out of scope until a named consumer, owner, business purpose, data minimization rule, and authorization model are approved. The employee-count route is intentionally absent; issue #215 removed it because no supported consumer was established.

## Versioning and compatibility

- `/api/v1` is the canonical prefix for new endpoints.
- Within `v1`, response field meaning and type are stable. Additive optional fields may be introduced; removing/renaming fields, changing types/semantics, or tightening authorization incompatibly requires a new version or a separately announced migration.
- Keep `/api/user` as a compatibility alias while existing consumers are inventoried. Do not remove it until owners have confirmed migration and a deprecation window has been communicated. New integrations must use `/api/v1/user`.
- API responses use explicit JSON DTOs, not raw Eloquent model serialization. Do not add model fields by default.

## Authentication, scopes, revocation

- Use Laravel Sanctum bearer tokens for non-browser integrations and Sanctum's supported first-party session flow where appropriate. Never put bearer tokens in URLs.
- Versioned endpoints must declare the least-privilege token ability they require. `GET /api/v1/user` requires `profile:read`; no token-issuing endpoint is added by this proposal.
- Revoke a compromised or retired personal access token by deleting/revoking that token record. Rotate by provisioning a replacement with only the required abilities, updating the consumer's secret store, verifying the replacement, and revoking the old token. Never log or commit plaintext tokens.
- Sanctum's configured token expiration is currently unset (`null`). Production operators must define and document an expiration/rotation schedule for machine tokens before onboarding external consumers; this change does not silently alter existing token lifetimes.

## Errors, throttling, retries

- Authentication failure: HTTP `401`, JSON message `Unauthenticated.`
- Missing required token ability: HTTP `403`.
- Validation failures for future endpoints must use Laravel's JSON validation contract (`422`, `message`, and field-keyed `errors`). Controllers must validate inputs before invoking application actions.
- Unknown resources: HTTP `404`; rate limit exceeded: HTTP `429` with Laravel's standard retry headers where supported.
- `api-v1` is limited to 60 requests per minute per authenticated user, or per IP for unauthenticated traffic. Authentication middleware runs before the versioned endpoint; unauthenticated requests are denied regardless of the limiter.
- The current endpoint is read-only, so idempotency keys, pagination, webhook signatures/replay protection, and write retries are not applicable. Any future create/command endpoint must define idempotency semantics before release. Add signed, timestamped, replay-protected webhook verification only if a named consumer requires inbound webhooks.

## Documentation and ownership

This file is the current API inventory and contract. Every new route must update its consumer, accountable owner, data scope, authentication/abilities, request and response schema, error behavior, rate limit, retry/idempotency strategy, and deprecation plan. Generate OpenAPI only when the approved surface grows enough to justify maintaining a generated schema; until then, keep this concise contract in source control.

Review the inventory whenever an integration is proposed and at least once per release. No external integration owner is established in the current repository; assign one before issuing production credentials.
