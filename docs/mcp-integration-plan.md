# Management-OS MCP integration: implementation plan

**Status:** Proposed architecture and delivery plan  
**Repository:** `deputy-proxy/Management-OS`  
**Target deployment:** Existing Management-OS Laravel application  
**Transport target:** Remote MCP over HTTPS using Streamable HTTP, subject to SDK and ChatGPT-client compatibility verification  
**Planning baseline:** 2026-11-19

## 1. Executive summary

Add a secure MCP server to the existing Management-OS Laravel modular monolith. ChatGPT will connect to the existing application and use explicitly exposed tools to read management information and, in later releases, request a limited set of controlled business actions.

Laravel remains the system of record. MCP is an integration boundary, not a second business-logic layer and not a generic database/API proxy. Tool handlers must call existing application/domain services or introduce reusable application actions where a genuine gap exists. They must not query or mutate Eloquent models as an alternative to the application's domain workflows.

Delivery is incremental:

1. Establish the authenticated MCP transport, dedicated AI identity, authorization, audit, approvals and retry safety.
2. Expose read-only executive, finance, CRM and operations capabilities.
3. Add independently approved task and CRM mutations, then management resources and prompts.
4. Expand to HR, products, marketing and academic operations after domain-specific privacy and permission review.

The first usable release is read-only. Payroll, invoice issuance, financial corrections, user administration and other high-impact operations are not enabled as AI mutations in this plan.

## 2. Verified repository context and constraints

The current repository describes itself as a Laravel modular monolith with Filament as its internal management UI. The current README records PHP `^8.4.1`, Laravel `^13.0`, Filament `^5.11.4`, Livewire 4, and Node.js 22. Confirm exact installed/runtime versions against the lockfile, CI and target hosting before choosing dependencies or deployment settings.

Relevant existing boundaries:

- Laravel/Eloquent owns authoritative business state and business rules.
- The current API is deliberately narrow: the README documents a Sanctum-protected versioned `GET /api/v1/user` self-profile endpoint and a protected compatibility `GET /api/user` route. MCP must not silently expand this into generic CRUD.
- The repository's `.github/AI_DEVELOPMENT_RULES.md` requires issue-driven work, dedicated issue branches, persistent Railway development Sandbox use, focused PRs, server-side authorization, regression coverage, CI verification and documentation updates.
- The configured CI gates include Composer validation/install, PHP syntax parsing, Pint tests, `composer test`, `composer analyse`, dependency audit, frontend build, SQLite migration/tests and a MySQL 8.0 migration/integrity job. The exact production PHP/MySQL versions must be verified before release.
- Existing authorization uses the project's HexaLite role/permission integration. MCP must map to the current permission model rather than inventing an independent authority hierarchy.
- Existing domain/query services include executive overview, financial overview, receivables/payables, cash forecast, budget-versus-actuals, profitability, customer dashboard, KPI aggregation, and tasks/projects. Inspect current signatures, auth-context dependencies and data semantics before wiring tools.
- Finance services intentionally preserve ambiguity around currency, invoice status and legacy data. Unknown, unresolved or incomparable values must remain explicit; never silently convert unknown to zero or aggregate unlike currencies/units.
- Existing payroll and invoice workflows have lifecycle, validation, audit and correction requirements. MCP must not bypass them.
- Existing issues #246 (authorization hardening), #247 (sensitive-data-safe audit policy), #251 (domain actions/events), #252 (versioned API/integration boundary), #253 (operations/recovery) and #257 (umbrella production-readiness roadmap) are relevant dependencies or coordination points.
- Issues #297 and #299 establish Market Trends persistence/admin work; they explicitly leave the future MCP ingestion tool out of scope. This programme must not claim that live market data collection already exists.

If current implementation differs from this planning baseline, implementation issues must preserve the intent and acceptance criteria while reconciling the plan against the actual code. Do not treat this document as proof that an integration, hosting capability, SDK version or production control is already implemented.

## 3. Product goals and non-goals

### Goals

- Allow ChatGPT to retrieve permission-filtered, current Management-OS business information.
- Give management a reliable way to ask cross-domain questions using authoritative application data.
- Expose narrow, named tools with stable schemas, explicit periods, provenance and structured errors.
- Allow a small set of low-risk operational writes only through existing domain workflows and a separate human approval step.
- Preserve attribution, authorization, audit history, data integrity, idempotency and privacy.
- Keep the MCP server deployable with the existing Laravel application.

### Non-goals

- Replacing Laravel, Filament, the database or the existing domain services.
- Splitting the monolith into microservices.
- Creating generic `list_models`, arbitrary SQL, generic Eloquent CRUD or unrestricted file/database access.
- Giving the AI account Owner, superadmin or equivalent privilege.
- Treating ChatGPT's conversational confirmation as proof of authorization inside Management-OS.
- Enabling payroll calculation/approval, invoice issuance/submission, financial reversals/corrections, role administration or other high-impact writes in the initial releases.
- Treating market estimates, search indices or third-party campaign metrics as verified facts without provenance and quality labels.

## 4. Target architecture

### Deployment

Run the MCP endpoint inside the existing Management-OS Laravel application, behind HTTPS and the existing application deployment/operations controls. The intended endpoint is `/mcp`, using remote MCP Streamable HTTP if supported by the selected, pinned PHP SDK and the ChatGPT connector/client.

Do not add a separate Node service by default. First verify PHP/runtime compatibility, HTTP transport support, request lifecycle behavior, hosting limits, reverse-proxy behavior, session/state requirements and timeout limits. If the current hosting cannot reliably support the required transport, document evidence and propose the smallest viable infrastructure adjustment before implementation.

### Logical request path

```text
ChatGPT
  -> HTTPS /mcp (Streamable HTTP)
  -> MCP transport and protocol adapter
  -> OAuth/authentication middleware
  -> dedicated AI service identity
  -> MCP tool authorization + record-level scoping
  -> validated DTO / tool handler
  -> Laravel application/domain service or action
  -> existing policies, transactions and persistence
  -> structured result
  -> MCP execution audit record
```

For a controlled write:

```text
ChatGPT requests action
  -> validate and preview exact operation
  -> create pending approval bound to payload hash
  -> authorized human reviews/approves in authenticated Management-OS UI
  -> approval is checked, unexpired and single-use
  -> existing domain action executes with idempotency key
  -> audit records AI actor, approver, action and outcome
  -> structured result returned to ChatGPT
```

### Proposed code organization

Adapt to established repository conventions rather than creating empty abstractions for their own sake.

- `app/Mcp/Server`: MCP server registration, transport/protocol adapter and capability registration.
- `app/Mcp/Authentication`: client authentication, identity resolution and token lifecycle integration.
- `app/Mcp/Authorization`: tool permissions, domain permission mapping, record scope and field filtering.
- `app/Mcp/Tools/{Executive,Finance,CRM,HR,Operations,Products,Marketing,Academic}`: focused tool handlers.
- `app/Mcp/Resources`: permission-aware resource providers.
- `app/Mcp/Prompts`: prompt templates that invoke tools and disclose reporting context.
- `app/Mcp/Approvals`: approval request lifecycle and exact-payload binding.
- `app/Mcp/Audit`: execution audit and correlation.
- `app/Mcp/DTOs`: validated input/output contracts.
- `routes/mcp.php` and `config/mcp.php`: route and integration configuration, if consistent with route conventions.
- Candidate persistence: MCP execution logs and MCP approval records, with migrations, retention policy and indexes.

Avoid introducing a second authorization framework. Keep MCP permission mapping explicit and testable.

## 5. Authentication and AI identity

Create a dedicated Management-OS service account for the integration (proposed label: **AI Assistant**; proposed email such as `ai-assistant@edventure.ro` is an example only and must be provisioned/confirmed by the system owner).

Requirements:

- The account is a technical principal, not a human owner.
- It must not have Owner, superadmin, blanket panel access or inherited owner bypass.
- It receives only explicit MCP and underlying domain permissions.
- Credentials/client registrations are separate from normal staff credentials and can be revoked and rotated.
- Use OAuth 2.1 authorization-code flow with PKCE if supported by the selected SDK and the ChatGPT MCP client. Verify exact client compatibility and redirect/metadata requirements before committing to the implementation. Do not invent a custom OAuth protocol if a supported, secure Laravel integration is available.
- Validate issuer/audience/client, scopes, token expiry, revocation, redirect URI, state/PKCE and replay protections as applicable to the chosen flow.
- Enforce HTTPS; do not place secrets in source control, URLs, logs or tool outputs.
- Resolve each request to the dedicated Management-OS principal and carry that principal through application authorization and audit.
- Revoke access immediately when the integration is disabled or compromised.

A service account is not a human approval identity. Where a business action requires a human decision, record the actual approving staff user separately.

## 6. Authorization model

Authorization has three distinct layers:

1. **Connection authentication:** may this registered ChatGPT client establish an MCP session?
2. **Tool and record authorization:** may this AI principal call this tool and see these exact records/fields?
3. **Human approval:** has an authorized human approved this exact proposed mutation where approval is required?

Rules:

- Deny by default.
- Every tool declares required MCP permission(s), underlying domain permission(s), allowed record scope and sensitive-field policy.
- MCP permissions may narrow existing access but must never grant more than the underlying Management-OS permission.
- Apply authorization on the server for every call; UI visibility is not a security boundary.
- Re-check permissions at execution time, not only when an approval is requested.
- Filter list/search results before aggregation where counts could reveal restricted records.
- Do not leak restricted data through errors, totals, resource URIs, autocomplete, relationships or prompts.
- Do not grant the AI identity Owner or broad administrator permissions to make integration easier.
- Test unauthenticated, insufficient-scope, unauthorized-record, cross-tenant/organizational-scope (where applicable), sensitive-field and revoked-token cases.

A permission matrix must be maintained alongside tool definitions. Initial permission groups should be granular (for example `mcp.executive.read`, `mcp.finance.read`, `mcp.crm.read`, `mcp.operations.read`, `mcp.tasks.create`, `mcp.tasks.update`, `mcp.crm.interactions.create`). Final names must follow the repository's actual permission naming and seeding conventions.

## 7. Human approval, audit and retry safety

### Approval

ChatGPT's message that a user said "yes" is not a trusted authorization artifact. For controlled mutations:

- Create a pending approval request containing actor, action/tool, target record, validated arguments, human-readable preview, creation/expiry times and a cryptographic hash of the canonical payload.
- Present the request in an authenticated Management-OS UI to a human who has the relevant approval permission.
- Require explicit approval or rejection in that UI.
- The AI service account cannot approve its own request.
- Bind approval to the exact payload hash; any changed argument requires a new approval.
- Make approval single-use and expiring.
- Re-authorize the action immediately before execution.
- Record approver identity and decision time. If a human initiator is known, record it separately; never infer or fabricate it.
- Handle rejected, expired, cancelled, duplicate and partially failed operations explicitly.

If a future delegated-human OAuth model is considered, it must provide a verifiable Management-OS human identity and cannot rely on conversational text alone.

### Audit

Record, at minimum:

- AI service-account ID and OAuth client ID;
- tool/action name and version;
- affected record type and identifier;
- request/correlation ID and idempotency key;
- human initiator, if known, and human approver when applicable;
- approval record ID and payload hash where applicable;
- execution timestamp, result state and safe error code;
- relevant domain event/reference IDs.

Do not log access tokens, secrets, full sensitive payloads, payroll details, student personal data or unnecessary personal information. Coordinate with #247 and the existing activity-log policy. Define retention and access controls before production enablement.

### Idempotency and failure handling

- Require an idempotency key for mutation requests.
- Persist the key with actor, tool and canonical request hash.
- Same key + same payload returns the original outcome; same key + different payload is rejected.
- Use transactions and existing domain lifecycle services for atomic state changes.
- Define timeout, retry, partial failure and duplicate approval behavior.
- Never blindly retry a non-idempotent action after an uncertain response.
- Include stable error codes and safe, actionable messages without leaking internals.

## 8. Initial tool catalogue

Tool names below are proposed contracts, not claims that these capabilities are already implemented. Each tool must have a strict input schema, bounded pagination, explicit filters, documented units/timezone, structured output and permission tests. Prefer small domain-specific tools over one broad "get everything" operation.

### Executive and management (Release 2)

- `get_executive_overview`
- `get_management_kpis`
- `get_kpi_alerts`
- `get_business_health`
- `get_management_priorities`
- `get_period_comparison`

Reuse verified overview/KPI services such as `GeneralOverviewData`, `KpiAggregationService` and existing KPI definition/query services. Require explicit reporting period and timezone where relevant. Include data freshness and missing-data indicators. Never reinterpret missing data as zero.

### Finance (Release 2, read-only)

- `get_financial_overview`
- `get_transactions`
- `get_receivables`
- `get_payables`
- `get_cash_forecast`
- `get_budget_vs_actuals`
- `get_profitability_report`
- `get_reconciliation_status`
- `get_invoice_details`

Reuse existing financial query/report services. Preserve currency boundaries, source provenance, invoice/payment statuses, unresolved legacy records and unknown values. Do not combine unlike currencies, units or incompatible invoice/product totals. Do not present estimates or forecasts as actual cash. No finance mutations in the initial programme.

### CRM and sales (Release 2 read; Release 3 limited writes)

Read:
- `search_customers`
- `get_customer_details`
- `get_customer_contracts`
- `get_customer_interactions`
- `get_sales_pipeline`
- `get_lead_details`
- `get_customer_economics`

Write candidates:
- `create_customer_interaction`
- `create_customer_lead`

Use existing customer dashboard/economics, attribution-history and lead workflow services. Protect personal and commercially sensitive fields. Lead conversion is a later, separately reviewed workflow and must reuse the existing conversion action.

### Operations and projects (Release 2 read; Release 3 limited writes)

Read:
- `get_projects`
- `get_project_details`
- `get_tasks`
- `get_task_details`
- `get_overdue_tasks`
- `get_operational_bottlenecks`
- `get_quarterly_objectives`
- `get_meetings`

Write candidates:
- `create_task`
- `update_task` (allowlisted fields only)
- `assign_task`
- `complete_task`

Reuse `TasksAndProjectsData`, task resources/actions and escalation services. Enforce project/task visibility, assignment eligibility, lifecycle rules and optimistic/concurrency safeguards where the domain requires them.

### HR and payroll (Release 4, limited read)

- Employee overview, approved evaluation summaries, timesheet status and payroll period status/validation exceptions only.
- Do not expose unrestricted employee records, bank details, identifiers, salary/payslip line detail or sensitive HR notes by default.
- No payroll creation, calculation, approval, close, correction or payment mutations through the initial MCP programme.
- Require explicit HR/finance owner approval of field-level access and retention before enabling any HR tools.

### Products, marketing and academic operations (Release 4)

- Products: approved product, price, cost and profitability views, preserving effective dates, currencies and calculation provenance.
- Marketing: campaign performance, Google Ads imports, competitor and Market Trends observations, always showing source, observed period, units, quality and freshness. Market Trends ingestion is a separate future capability related to #297/#299, not assumed to exist.
- Academic: limited student/group/session/attendance operational summaries with strict student privacy, role scope and minimum necessary fields. No bulk student export by default.

## 9. MCP resources and prompts

Resources (permission-aware and generated from live application data) may include:

- `management://overview`
- `management://kpis`
- `finance://reporting-rules`
- `finance://current-position`
- `operations://priorities`
- `company://departments`
- `company://policies`

Prompts may include:

- `daily_ceo_briefing`
- `weekly_management_review`
- `financial_review`
- `operations_review`
- `business_growth_review`

Prompts are workflow templates, not a substitute for live tool calls. They must identify the requested period, cite/retain source timestamps and caveats in the returned content, distinguish facts from analysis, and disclose unavailable or restricted information. Resource URIs must not bypass tool authorization or expose sensitive data.

## 10. Delivery phases and release gates

### Release 1: Secure MCP foundation

Issues: server/transport, identity/authentication, authorization mapping, audit/approval/idempotency.

Exit criteria:
- A compatible MCP client connects over HTTPS to the existing application.
- No unauthenticated business data is available.
- Dedicated AI identity is least-privileged and revocable.
- Owner/superadmin bypass is not inherited.
- Tool authorization is server-side and record-scoped.
- Execution audit and mutation approval records are tested.
- No write tool is enabled until the approval workflow is complete.

### Release 2: Read-only management intelligence

Issues: executive, finance, CRM and operations read tools.

Exit criteria:
- Read tools reuse domain services and have structured schemas.
- Data scopes, freshness, periods, currencies, units and unresolved states are explicit.
- Sensitive fields and unauthorized records are filtered.
- Tests cover positive and denial cases.
- No finance/payroll mutation is exposed.

### Release 3: Controlled operational actions

Issues: task/CRM mutations, resources and management prompts.

Exit criteria:
- Exact-payload approval in Management-OS UI is mandatory for configured writes.
- AI cannot approve its own requests.
- Domain workflows, idempotency, audit and retry behavior are covered.
- Initial writes remain limited to tasks and CRM interactions/leads.
- Prompts/resources respect tool permissions and disclose source freshness.

### Release 4: Extended domain coverage

Issue: HR, products, marketing and academic tools.

Exit criteria:
- Domain owners approve field-level access and data retention.
- Sensitive HR/student data is minimized.
- Source provenance and measurement semantics are preserved.
- Each domain has dedicated tests, docs and explicit enablement permissions.

## 11. Issue programme and dependencies

Create one MCP umbrella issue and focused implementation issues. The GitHub issue numbers are assigned by GitHub and must be linked after creation.

1. **MCP umbrella:** Build the secure AI business-management integration, coordinate dependencies and release gates.
2. **Server foundation:** compatible PHP MCP SDK, Laravel registration, HTTPS Streamable HTTP endpoint, protocol lifecycle, capability registration, validation and transport tests.
3. **Identity/authentication:** dedicated AI account, OAuth/client lifecycle, revocation/rotation and identity propagation.
4. **Authorization:** MCP permission map, underlying domain permission enforcement, record scoping and field-level redaction.
5. **Audit/approvals/idempotency:** execution audit, human approval UI/workflow, payload binding, single-use expiry and safe retries.
6. **Executive read tools:** overview, KPIs, alerts, business health, priorities and period comparison.
7. **Finance read tools:** finance overview, transactions, receivables/payables, cash forecast, budget actuals, profitability, reconciliation and invoice detail.
8. **CRM read tools:** customer/lead/contract/interaction/pipeline/economics reads.
9. **Operations read tools:** projects/tasks/overdue/bottlenecks/objectives/meetings reads.
10. **Controlled mutations:** task create/update/assign/complete and CRM interaction/lead create, with approval and audit. Lead conversion is not automatically included.
11. **Resources and prompts:** management resources and CEO/weekly/finance/operations/growth prompt templates.
12. **Extended domains:** HR, products, marketing and academic tools after domain privacy review.
13. **Documentation and operations:** complete operator/developer documentation, ChatGPT connection guide, permission matrix, incident/revocation procedure, monitoring, deployment and recovery instructions, updated as each implementation issue lands.

Dependency order:
- Foundation issues 2–5 are sequentially gated: transport -> identity -> authorization -> approval/audit controls (the implementation may parallelize independent tests but not bypass gates).
- Read-domain issues 6–9 depend on transport, identity and authorization.
- Controlled mutation issue 10 depends on approval/audit/idempotency and the relevant CRM/operations read/domain workflows.
- Resources/prompts issue 11 depends on the reporting tools and permission model.
- Extended domains issue 12 depends on the secure foundation and domain-owner review.
- Documentation issue 13 is cross-cutting and must be updated with each issue, with final operator docs gated on implemented behavior.
- Coordinate with #246, #247, #251, #252, #253 and #257. Do not duplicate or silently replace their scope. Reconcile #297/#299 for Market Trends ingestion.

## 12. Testing and validation strategy

For each issue:

- Inspect current route, middleware, service signatures, auth-context dependencies, models, migrations, policies, resources and tests before coding.
- Add unit/feature tests for valid, invalid, unauthorized, revoked, expired, out-of-scope, duplicate, stale and failure cases relevant to the feature.
- Test MCP protocol initialization, tool discovery, schema validation, transport errors, request limits and timeout behavior.
- Test OAuth/PKCE and token revocation against the actual supported ChatGPT client flow.
- Test that the AI principal cannot use Owner/superadmin privileges or bypass domain policies.
- Test record-level scoping and sensitive-field redaction, including aggregates/counts.
- Test approval payload hash mismatch, expiry, reuse, self-approval denial, permission revocation between approval and execution, and concurrent execution.
- Test idempotency: same key/same payload, same key/different payload and retry after uncertain response.
- Test financial outputs for currency, units, provenance, unknown/unresolved states and missing data.
- Test HR/student privacy boundaries and avoid real personal data in fixtures.
- Run focused tests and the repository's configured CI validation. The repository rules identify `composer validate --no-check-publish`, `composer install --no-interaction --prefer-dist --no-progress`, PHP syntax parsing, `vendor/bin/pint --test tests`, `composer test`, `composer analyse`, `composer audit --locked`, `npm ci`, `npm run build`, SQLite migrations/tests and MySQL 8.0 migration/integrity coverage. Follow the actual workflow for exact environment and command order.
- Inspect actual GitHub Actions results. A local pass is not a substitute for green required CI.

## 13. Security, privacy and operational readiness checklist

- [ ] HTTPS endpoint and secure reverse-proxy configuration verified.
- [ ] SDK and transport version pinned after compatibility review.
- [ ] OAuth/client configuration documented; credentials stored outside source control.
- [ ] Dedicated AI identity provisioned, least-privileged and revocable.
- [ ] Owner/superadmin privilege explicitly denied.
- [ ] Per-tool and per-record authorization tests pass.
- [ ] Approval UI requires an authorized human distinct from the AI principal.
- [ ] Approval is bound to canonical payload hash, single-use and expiring.
- [ ] Idempotency and uncertain-outcome handling tested.
- [ ] Audit fields and safe logging policy reviewed with #247.
- [ ] Retention and access policy defined for MCP logs and approvals.
- [ ] Rate limits, request size limits, timeouts and concurrency limits configured.
- [ ] Monitoring and alerts cover auth failures, tool failures, approval backlog, latency and error rates.
- [ ] Token revocation and incident response runbook tested.
- [ ] Backup and restore coverage includes any new durable approval/audit tables.
- [ ] Production PHP/database/hosting compatibility verified; do not infer from CI alone.
- [ ] No sensitive data in tool descriptions, errors, prompts, logs or fixtures.
- [ ] Documentation matches deployed behavior.

## 14. Definition of done

The MCP integration is complete only when:

- ChatGPT connects to the existing Management-OS application over the verified secure remote transport.
- The dedicated AI account is authenticated, revocable and least-privileged.
- Laravel remains the only business source of truth.
- Every tool is narrowly scoped, validated, permission-checked and documented.
- Existing domain services/actions own business logic and mutations.
- Financial ambiguity, currency boundaries, historical truth and data provenance are preserved.
- Any enabled write is independently approved by an authorized human in Management-OS, bound to the exact payload, idempotent and audited.
- Privacy controls cover finance, HR, students and customer data.
- Regression tests and all required CI checks pass.
- Deployment, monitoring, revocation, incident response, backup and recovery documentation are verified against the real environment.
- The relevant PRs are merged and issues closed only after actual CI and release criteria are satisfied.

## 15. Open decisions to resolve during implementation

These are verification tasks, not assumptions:

1. Which PHP MCP SDK/version supports the required Streamable HTTP server transport on the repository's PHP/Laravel baseline?
2. Does the current ChatGPT MCP client support the exact OAuth 2.1/PKCE discovery and authorization flow selected?
3. Does the target hosting support persistent/streaming HTTP responses, request duration, concurrency and proxy behavior required by the transport?
4. Which current HexaLite permissions and role-seeding conventions should map to the AI principal?
5. Which staff roles may approve each class of task/CRM mutation?
6. What retention period and access policy apply to MCP execution and approval records?
7. Which executive/finance/CRM/operations fields are approved for AI disclosure?
8. Which production runtime/database versions and operational controls are actually deployed?

Do not resolve these questions by guessing. Record verified answers in implementation PRs and update this document when decisions become authoritative.
