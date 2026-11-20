# MCP permission matrix

**Matrix version:** `1.2.0` (configured by `mcp.permission_matrix_version`)

This document is the operator-facing companion to `config/mcp.php`. The configuration is the runtime source of truth. Every registered MCP tool must have exactly one entry under `mcp.tool_permissions`; the server refuses to register a tool without one.

## Authorization layers

Every tool call must pass all of the following checks, on every invocation:

1. The request is authenticated by Passport and has the server-level `mcp:use` scope.
2. The authenticated user is the configured `MCP_AI_PRINCIPAL_EMAIL` technical identity.
3. The OAuth access token contains the tool-specific scope declared by the matrix entry.
4. The AI identity has exactly one global HexaLite Assistant role for the configured domain-permission guard.
5. The AI identity does not have the configured HexaLite Owner role.
6. Every required domain permission in the matrix is present in the AI identity's current HexaLite role permissions.
7. The declared record-scope implementation is applied before query results, pagination, totals, counts or aggregations are produced.
8. A record loaded by an externally supplied identifier is checked again against the declared record scope.
9. Tool output is filtered through its explicit field allowlist. Nested relationships require their own nested allowlists.

The tool permission is an additional restriction, not a replacement for the domain permission. Both the OAuth tool scope and every required `domain_permissions` entry are required. Optional domain permissions are not part of the tool invocation gate: handlers must check them independently before querying each optional source. This supports permission-filtered overview tools without requiring the AI identity to have every underlying resource permission. The MCP authorization path deliberately does not use HexaLite's implicit no-role or Owner bypasses. The domain-permission guard defaults to the application's configured default guard (`web`); set `MCP_DOMAIN_PERMISSION_GUARD` only if the deployment deliberately uses a different guard.

## Matrix entry contract

Add an entry to `config/mcp.php` for each tool:

| Field | Requirement |
| --- | --- |
| `mcp_scope` | A Passport scope dedicated to the capability. It is registered from the matrix and must be present on the caller's token. |
| `domain_permissions` | Non-empty list of exact existing HexaLite permission keys. All listed permissions are required to invoke the tool. |
| `optional_domain_permissions` | Optional list of exact existing HexaLite permission keys. These are included in the Assistant-role provisioning allowlist but are checked individually by the handler; they must not be treated as granted merely because the tool itself is callable. |
| `record_scope` | Class implementing `App\Mcp\Authorization\Scopes\McpRecordScope`. It must scope collection queries and authorize individual records. |
| `allowed_fields` | Non-empty nested allowlist. Only listed scalar fields are returned. Nested objects/relationships must declare their own field map. |
| `operation_type` | Required. Declare `read` or `write` for every registered tool. Direct registration through `addAuthorizedTool()` is read-only. |
| `requires_approval` | Must be `true` for every `write` tool. Write request tools must use `addApprovalRequestTool()`; execution must use the approved execution service. |

Example only. Do not add it until the named tool, domain service, record scope, and field contract are implemented and tested:

```php
'tool_permissions' => [
    'get_tasks' => [
        'mcp_scope' => 'mcp.operations.tasks.read',
        'domain_permissions' => ['task.index'],
        'record_scope' => \App\Mcp\Authorization\Scopes\McpTaskRecordScope::class,
        'operation_type' => 'read',
        'requires_approval' => false,
        'allowed_fields' => [
            'id' => true,
            'title' => true,
            'status' => true,
            'due_date' => true,
            'assignee' => [
                'id' => true,
                'name' => true,
            ],
        ],
    ],
],
```

## Implementing a tool

- Register each capability registrar in mcp.capability_registrars, and implement it by extending `AuthorizedMcpCapabilityRegistrar`.
- Register read-only tools with `addAuthorizedTool()`. It rejects permission-matrix entries marked as writes. Controlled write request tools must declare `operation_type => write` and `requires_approval => true`, then use `addApprovalRequestTool()`; the execution tool must use `addApprovedExecutionTool()`, which accepts only the approval ID and dispatches the stored payload through `McpApprovalService::executeApproved()`. Do not call the MCP SDK's `Builder::addTool()` directly from application capability code.
- The wrapper checks the current API-authenticated principal and the permission matrix on every call, then filters the returned structured data.
- For list/search/report tools, call `scopeQuery()` before applying user filters, pagination, `count`, `sum`, grouping or other aggregation.
- For direct record lookups, call `authorizeRecord()` after loading the record and before returning it or passing it to a domain action.
- Use a domain-specific `McpRecordScope` where existing application visibility rules are more complex than ownership. Do not substitute a global scope for convenience.
- Call existing application/domain services for business logic. MCP handlers must not implement a parallel business workflow or use raw model access to bypass service authorization.
- Keep tool output structured as arrays or `Arrayable` DTOs. Do not return raw models, arbitrary serialized relationships, exceptions or debug information.

## Supported record scopes

- `McpGlobalRecordScope`: explicit full-domain scope. Use only when the underlying domain permission is itself a reviewed all-record permission.
- `McpOwnedRecordScope`: filters by `owner_id`. Use only for models whose authoritative visibility rule is exactly ownership.
- `McpTaskRecordScope`: mirrors the current task visibility rule: `task.view_all` sees all tasks; otherwise the principal sees tasks assigned to them or created by them.

These scopes are not interchangeable. Add a dedicated scope implementation when the domain's real visibility rule differs.

## Sensitive data

The default is exclusion. Do not expose finance, HR, student, customer, employee, payroll, banking, authentication, or other personal/sensitive fields unless the exact field is explicitly approved and added to a tool's allowlist. Relationships are excluded unless nested allowlists name their permitted fields. A parent field allowlist never implicitly authorizes nested data.

Counts and aggregates must be calculated only after the record scope has been applied. Do not return unfiltered totals, result counts, pagination metadata, existence checks, search suggestions or error messages that reveal restricted records.

## Principal provisioning

`php artisan mcp:provision-principal` verifies that the configured AI identity has exactly one global `Assistant` role, no Filament panel access, and no domain permissions beyond the union of domain permissions declared in the MCP matrix. With an empty matrix, the Assistant role must remain permissionless. Adding a new tool therefore requires updating the matrix and reviewing the AI role's explicit permissions together.

Never assign Owner/superadmin or copy a human user's role set to the AI principal. A Passport tool scope alone does not grant the underlying domain permission.

## Current state

Issue #533 registers six read-only executive tools. Issue #534 adds nine read-only finance tools. Executive tools use `overview-dashboard.view` (with `kpi_alert.view_own` for alerts); finance tools declare granular permissions for dashboard, transactions, receivables/payables, forecasts, budgets, profitability, reconciliation and invoice detail. Optional source permissions are evaluated independently. The AI principal receives no domain permissions automatically. Operators must explicitly assign only reviewed permissions declared in the runtime matrix. See [MCP executive tools](mcp-executive-tools.md) and [MCP finance tools](mcp-finance-tools.md) for schemas, sources, and limitations.


## Human approval and audit permissions

Issue #532 adds three separate Management-OS staff permissions:

- `mcp_approval.index` — view pending and historical approval requests and their safe previews.
- `mcp_approval.approve` — approve or reject a pending operation with a required rationale.
- `mcp_execution_audit.index` — view execution and approval audit history.

Assign these only to designated human reviewers/auditors. The configured AI principal is explicitly denied access even if it is accidentally assigned one of these permissions. The Filament resources are read-only except for the approve/reject actions; model/service guards preserve the same boundary server-side. See [MCP approval and execution audit controls](mcp-approvals-and-audit.md).

All fifteen read-only executive and finance tools are registered with explicit tool scopes, required and optional domain permissions, record scopes, and output-field allowlists. No write tools are enabled. Each future write tool must use payload-bound approval, an independent human reviewer, execution-time authorization, and an existing domain service.


## Finance tool permission matrix

All nine finance tools are read-only. They use existing financial query/report services and dedicated OAuth scopes. A missing optional source permission produces an unavailable or omitted section, never an inferred zero.

| Tool | OAuth scope | Required permissions | Optional source permissions |
| --- | --- | --- | --- |
| `get_financial_overview` | `mcp.finance.overview.read` | `finance-dashboard.view` | `transaction.index`, `customer_invoice.index`, `vendor_invoice.index` |
| `get_transactions` | `mcp.finance.transactions.read` | `transaction.index` | `financial_account.index` |
| `get_receivables` | `mcp.finance.receivables.read` | `receivables-payables.view`, `customer_invoice.index` | `transaction.index` |
| `get_payables` | `mcp.finance.payables.read` | `receivables-payables.view`, `vendor_invoice.index` | `transaction.index` |
| `get_cash_forecast` | `mcp.finance.cash-forecast.read` | `cash-forecast.view`, `transaction.index`, `customer_invoice.index`, `vendor_invoice.index` | None |
| `get_budget_vs_actuals` | `mcp.finance.budget-actuals.read` | `budget.actuals.view`, `budget.index`, `transaction.index` | None |
| `get_profitability_report` | `mcp.finance.profitability.read` | `finance-profitability.view` | `product.index` |
| `get_reconciliation_status` | `mcp.finance.reconciliation.read` | `financial-reconciliation.view` | `transaction.index`, `financial_account.index` |
| `get_invoice_details` | `mcp.finance.invoice.read` | `receivables-payables.view` | `customer_invoice.index`, `vendor_invoice.index`, `transaction.index` |

Finance output allowlists exclude internal record IDs, source URLs, party names, addresses, tax identifiers, raw descriptions, allocation history, reconciliation hashes and free-text forecast assumptions. Payment/allocation values require `transaction.index`; account labels require `financial_account.index`. `get_cash_forecast` requires permissions for every included source.

The data contract is currency-safe and source-aware: no FX conversion or cross-currency total is inferred; unsupported amounts remain null or explicitly unavailable; legacy integer transaction amounts are labelled `legacy_integer_unverified`; vendor payable totals remain unavailable because the current source contract does not approve them. `get_budget_vs_actuals` uses a non-persisting preview and never creates a report snapshot. `get_cash_forecast` reads approved/retired scenarios and never generates a run. `get_profitability_report` reads completed immutable runs. Reconciliation candidates are suggestions and are never automatically accepted. See [MCP finance tools](mcp-finance-tools.md) for complete contracts and limitations.
