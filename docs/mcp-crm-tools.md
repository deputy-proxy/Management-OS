# MCP CRM and sales tools

**Issue:** #535
**Release:** 2, read-only management intelligence
**Permission matrix:** 1.3.0

The CRM registrar exposes seven read-only tools. Each tool is registered through AuthorizedMcpCapabilityRegistrar, requires its dedicated Passport scope and the listed current HexaLite domain permissions, applies its declared record scope before querying, and returns only the nested field allowlist in config/mcp.php.

## Tool catalogue

| Tool | Required domain permissions | Inputs |
| --- | --- | --- |
| search_customers | customer.index | query (2–100 characters); optional customer_type; sort_by (name, created_at, updated_at); sort_direction; limit (1–100); offset (0–1000) |
| get_customer_details | customer.index | customer_id |
| get_customer_contracts | customer.index, customer_contract.index | customer_id; optional limit, offset |
| get_customer_interactions | customer.index, customer_interaction.index | customer_id; optional start_date, end_date, limit, offset |
| get_sales_pipeline | customer_lead.index | No arguments |
| get_lead_details | customer_lead.index | lead_id |
| get_customer_economics | finance-unit-economics.view | Optional run_id (UUID), limit, offset |

All input schemas reject additional properties. Search/list pagination is bounded to 100 rows per request and offsets to 1,000. Search sorting is restricted to the explicit allowlist. Counts are calculated on the authorized query before the database applies the requested page.

## Output and privacy contract

- Customer search returns only customer ID, name, customer type and creation timestamp. It searches the customer name, not email or government identifiers.
- Customer details return only ID, name, customer type and creation/update timestamps.
- Contract results return contract code, start/end dates and signed timestamp. Contract documents, file paths and customer identifiers are excluded.
- Interaction results return timestamp and interaction rating. Initiator identity, free-text reason and description are excluded.
- Lead details return name, status, source category, campaign/referral attribution and timestamps, plus status/attribution history. Email, phone, topic/product interests, privacy-consent metadata, actor identities and converted customer identifiers are excluded. Status and attribution history are capped at 100 entries each; the response includes history counts and history_truncated.
- Sales-pipeline output is aggregate-only. It does not return lead names or contact details.
- Customer economics reads existing immutable runs. It returns period and policy version, exception-code counts, and aggregate source-category/currency results. Customer/lead IDs, source references, cost-entry IDs, revenue IDs and raw source snapshots are excluded.
- No tool creates or changes a customer, lead, contract, interaction, attribution record or unit-economics run.

## Metric semantics and source limitations

- Reporting timestamps use Europe/Bucharest.
- get_sales_pipeline.open_lead_count and lead_statuses reuse CustomerDashboardData and count leads with no converted_customer_id. The attribution section reuses the same service's attribution aggregation over non-soft-deleted leads; it therefore includes converted leads. The basis field makes this difference explicit.
- Attribution categories use the recorded source_category; unknown_source_count remains visible rather than being assigned to a known channel. Campaign/referral counts reflect populated current attribution fields, not inferred attribution.
- get_customer_economics selects the latest immutable run by database insertion order unless a specific run_id UUID is supplied. It does not invoke CustomerUnitEconomicsService::calculate(), which persists a new immutable run. Null CAC, revenue or LTV values remain null; revenue/retention coverage and unknown counts remain explicit. Incomplete results are not converted to zero.
- Missing customer, lead or economics run identifiers return an unavailable state. They do not expose whether a soft-deleted or otherwise inaccessible record exists.

## Authorization and operational notes

The tools use McpGlobalRecordScope because the underlying customer, contract, interaction and lead index permissions are current all-record visibility gates in the corresponding Filament resources. Contract and interaction tools require both the customer index permission and their specific resource index permission. Customer economics requires its dedicated report-view permission, not the ability to calculate or record costs/retention observations.

Operators must explicitly grant the Assistant role only the reviewed permissions above. The AI principal receives no permissions automatically. No CRM mutation is enabled by this release. Controlled CRM/task writes are separately governed by #537 and the human approval workflow.

See MCP permission matrix, MCP server, and MCP integration plan.
