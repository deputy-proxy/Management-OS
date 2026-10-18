# EdVenture Management OS

Management-OS is a Laravel monolith for EdVenture's administrative, finance, academic, portfolio, and operations workflows. Laravel/Eloquent owns application state and business rules; Filament provides the internal management UI. The project is organized into domain areas, not separately deployed services.

## Architecture map

| Area | Main locations | Responsibility |
| --- | --- | --- |
| HTTP boundaries | `routes/web.php`, `routes/api.php`, `app/Http/Controllers` | Public pages/forms and API endpoints. The API exposes the versioned Sanctum-protected `GET /api/v1/user` self-profile endpoint and retains `GET /api/user` as a protected compatibility route. See `docs/api-contract.md` for scopes, response fields, rate limits, and versioning policy. |
| Domain records | `app/Models`, `database/migrations` | Eloquent models, relationships, status rules, and schema/data migrations. |
| Admin UI | `app/Filament/Resources` | Filament resource forms, tables, filters, actions, and infolists for the management modules. |
| Overview/query services | `app/Services/GeneralOverviewData.php`, `FinancialOverviewData.php`, `ProductsOverviewData.php`, `TasksAndProjectsData.php`, `CustomerDashboardData.php` | Assemble domain data for management dashboards without making the UI the source of business logic. |
| Pricing and products | `ProductPriceService`, `ProductCostService`, `ProductSyncService`; `Product`, `ProductPrice`, `ProductCost`, `ProductRevenue` | Canonical product records, price selection, cost/revenue records, and synchronization with courses, camps, events, and workshops. See [product-price compatibility and migration notes](docs/product-prices.md). |
| Employee payroll | `EmployeePaySlipService`, `EmployeePaySlip`, `EmployeePaySlipItem`, `GenerateMonthlyEmployeePaySlips` | Monthly payslips, source-linked activity lines, immutable calculation snapshots, and signed corrections. See [the payslip workflow](docs/employee-payslip-workflow.md). |
| Scheduling | `Session`, `SessionAssistance`, `SessionAttendance`, `CalendarEventService`, `routes/console.php` | Unified scheduled sessions and related activity. `Session::session_type` distinguishes group and individual sessions. |
| Background/operational commands | `app/Console/Commands`, `app/Jobs` | Explicit backfills, scheduled payslip generation, and queued notification workflows. |
| Public views | `resources/views`, `PublicSubmissionController` | Homepage, shared navigation/footer components, and public enquiry, competition-entry, newsletter, and review forms. Legacy page routes without a view intentionally return 404 until implemented; see [public route availability](docs/public-site-routes.md). |
| Validation | `tests/Feature`, `tests/Unit`, `.github/workflows/tests.yml` | Regression tests for workflows, migrations, authorization, and data integrity. CI is the authoritative automated gate. |

### Important data-integrity boundaries

- `App\Models\Session` is the runtime model for the `scheduled_sessions` table. `session_type` distinguishes group and individual sessions. The old `GroupSession`/`IndividualSession` names remain only in the historical consolidation migration and its regression coverage, not as current runtime models.
- `ProductPrice` is the only runtime price model and the canonical price ledger. The old price tables remain as migration rollback/audit sources; legacy polymorphic aliases resolve to `ProductPrice`. See [docs/product-prices.md](docs/product-prices.md).
- Payslip JSON `items` is a legacy/manual breakdown. `manual_amount` preserves the existing baseline, while `employee_pay_slip_items` stores normalized source-linked activity and adjustment lines. These representations are intentionally not interchangeable; see [docs/employee-payslip-workflow.md](docs/employee-payslip-workflow.md).
- Migrations that preserve financial or historical records may reject rollback when it would discard audit data. Do not bypass those guards to force a schema rollback.
- The two wildcard Composer constraints are intentional local path-package exceptions; see [dependency constraint decisions](docs/composer-dependency-constraints.md) before changing them.

## Local development and contribution workflow

1. Read the GitHub issue and its implementation plan, then review [AI development rules](.github/AI_DEVELOPMENT_RULES.md), [.chatgpt/osingle.md](.chatgpt/osingle.md), the relevant workflow, and nearby tests before editing.
2. Work in the persistent repository development Sandbox. Use an issue branch named `[phase]-[issue]` (for example, `1-221`) and keep each PR limited to one issue. PR titles use `#[issue-number] implemented`.
3. Install dependencies from the committed lockfiles when they are missing or invalid:

   ```sh
   composer install --no-interaction --prefer-dist --no-progress
   npm ci
   ```

4. Prepare `.env` from `.env.example` and configure the database as needed. For the same local quality gates as CI, use the commands and environment defined in [`.github/workflows/tests.yml`](.github/workflows/tests.yml):

   ```sh
   composer validate --no-check-publish
   php scripts/check-documented-composer-scripts.php
   npm run build
   php artisan migrate --force
   vendor/bin/pint --test tests
   composer test
   composer audit --locked
   npm audit --audit-level=high
   ```

   CI also parses PHP files in `app`, `bootstrap`, `config`, `database`, `routes`, `scripts`, and `tests` using `token_get_all(..., TOKEN_PARSE)`. Follow the workflow for the exact syntax-check command and SQLite environment. `composer test` is the configured test script; PHP static analysis is not currently configured as a CI gate.

5. Add focused regression coverage for behavior or schema changes, run the focused tests, then run the complete local gate. Open/update the PR only after local validation passes, inspect the actual GitHub Actions result, and merge only when all required checks are green.

### Operational commands

- `php artisan products:backfill` reports missing product links without changing data. Add `--apply` only when an approved backfill should create/synchronize records.
- `php artisan employee-payslips:generate --month=YYYY-MM` generates or safely recovers a calendar month; the command is idempotent. The scheduler runs it on day 1 at 00:05 in the configured application timezone.

Do not infer missing business rules, rewrite historical records for convenience, or remove compatibility code before its runtime and migration references have been verified.
