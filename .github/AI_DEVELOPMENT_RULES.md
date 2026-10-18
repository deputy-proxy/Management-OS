# Management-OS AI Development Rules

## Purpose

This document defines the repository-level development rules for Management-OS. It is authoritative for implementation workflow, validation, scope control, and completion criteria unless a more specific repository or product specification explicitly overrides it.

## 1. Authoritative Sources

Before implementing an issue, inspect and reconcile:

1. The issue and its implementation plan.
2. This document.
3. The applicable GitHub Actions workflow(s), currently `.github/workflows/tests.yml`.
4. `composer.json`, `package.json`, and relevant tool configuration.
5. Existing implementation and tests around the affected functionality.
6. Authoritative architecture/domain specifications and the README.

Repository configuration and existing working patterns take precedence over generic Laravel/PHP conventions.

If the issue plan conflicts with the current repository state, do not blindly reproduce stale assumptions. Preserve the intent and acceptance criteria while adapting the implementation to the verified current state.

## 2. Issue-Driven Development

Every implementation must be tied to a GitHub issue.

For each issue:

1. Read the acceptance criteria and first-comment implementation plan.
2. Inspect the current repository before coding.
3. Create or prepare a dedicated issue branch.
4. Implement only the issue scope.
5. Add or update regression coverage when executable behavior changes.
6. Run the repository's required validation locally.
7. Push the branch and open/update the PR.
8. Inspect the actual GitHub Actions result.
9. Diagnose failures from the first meaningful error, not merely the last visible error.
10. Repeat local validation and CI verification after corrections.
11. Merge only after required checks are green.
12. Close the issue only after completion is verified.

Never implement directly on `main`.

## 3. Branching and Pull Requests

Implementation branches use the repository convention:

`[phase]-[issue]`

Example:

`1-1`

Pull request titles use:

`#[issue-number] implemented`

Each PR must contain only changes required for its issue. Do not mix unrelated refactoring, formatting, dependency upgrades, or speculative improvements into an issue PR.

## 4. Repository and Sandbox Workflow

Development work must be performed in the repository's persistent Railway development Sandbox.

The intended model is:

**one repository → one persistent Sandbox → many issue branches → many development loops**

Before implementation, use the repository preparation flow to verify that the repository-specific Sandbox exists and that the issue branch is correctly prepared.

Do not create a fresh Sandbox merely because a new issue is being processed.

Do not reuse another repository's Sandbox.

Do not destroy the repository Sandbox after completing an issue.

## 5. CI Is Authoritative

GitHub Actions is the authoritative automated validation environment.

A local passing result is necessary but is not sufficient to declare an issue complete.

Never claim that CI is green without inspecting the actual GitHub Actions result for the PR.

If CI fails:

1. Identify the failed job.
2. Identify the failed step.
3. Identify the exact command.
4. Find the first meaningful error.
5. Determine whether it is the root cause or a downstream consequence.
6. Trace the failure to the changed code/configuration.
7. Fix the root cause.
8. Re-run the relevant local validation.
9. Push the correction.
10. Inspect the new CI result.

## 6. PHP Validation

The current `composer.json` defines `setup`, `dev`, `test`, and `analyse` scripts. `composer test` and `composer analyse` are CI quality gates. The documented `lint:check`, `types:check`, and `ci:check` scripts do not exist and must not be used or described as current commands.

The PHP CI job in `.github/workflows/tests.yml` runs these checks in order:

1. `composer validate --no-check-publish`
2. `composer install --no-interaction --prefer-dist --no-progress`
3. Build the frontend for feature tests that render Blade views requiring Vite's manifest.
4. `php artisan migrate --force` against the configured SQLite CI database.
5. Parse PHP files under `app`, `bootstrap`, `config`, `database`, `routes`, and `tests` with `token_get_all(..., TOKEN_PARSE)`.
6. `vendor/bin/pint --test tests`
7. `composer test`
8. `composer analyse`
9. `composer audit --locked`

Use those configured checks for local validation; do not invent aliases that are absent from `composer.json`. CI also runs a separate MySQL 8.0 migration/integrity regression job and the PHPStan/Larastan gate.

PHP static analysis is enforced by the `static-analysis` CI job using PHPStan 2 and Larastan 3 at level 5 over `app`. `phpstan-baseline.neon` records the reviewed legacy findings present when the gate was introduced. New findings fail CI; baseline changes must be reviewed explicitly and must not be used to hide new issues. Retire fixed findings by removing their specific entries from `phpstan-baseline.neon` and confirming `composer analyse` still passes. Run `composer analyse` locally. The separate `mysql` CI job runs migrations and database-sensitive regression tests against MySQL 8.0. Production is documented as MySQL 8.x, but the exact deployed minor version remains unverified; CI coverage is not a substitute for staging verification against that exact version.

A lightweight CI check validates the Composer commands documented in these development instructions against actual Composer scripts and the explicitly allowed Composer CLI commands.

## 7. Frontend Validation

The frontend build is part of CI.

Use the Node version established by the repository workflow and install dependencies from the committed lockfile.

Current frontend validation is:

`npm ci`

followed by:

`npm run build`

Do not introduce an alternative frontend build command unless the repository configuration requires it.

## 8. Testing

When an issue changes executable behavior:

- add focused regression coverage;
- preserve existing tests;
- run the relevant focused tests;
- run the complete repository validation required by CI.

When an issue is documentation/configuration-only, new application tests are not required, but the affected configuration must still be validated.

Do not weaken or remove tests merely to make CI pass.

## 9. Architecture and Application Boundaries

Laravel owns authoritative business state, application rules, authorization, and historical records.

AI agents own reasoning and decisions, not direct database access.

MCP exposes controlled application capabilities. MCP must call application/domain services rather than duplicate business logic or manipulate persistence directly.

External services and automation tools are execution/orchestration boundaries and must not silently become authoritative sources of Management-OS business state.

Sensitive actions require explicit authorization and, where applicable, approval.

## 10. Authorization and Security

Authorization must be enforced server-side.

UI visibility is never a security boundary.

Sensitive mutations must use controlled application/domain workflows.

Never commit secrets, credentials, tokens, private keys, or environment-specific secrets.

External integrations must account for authentication, authorization, validation, retries, timeouts, idempotency, failure states, external identifiers, and auditability.

## 11. Historical and Data Integrity

Preserve historical truth.

Do not silently rewrite historical records merely because current rules have changed.

Important decisions, approvals, transactions, audit records, publication results, and external execution records must remain interpretable.

When a change affects historical interpretation, document the compatibility or migration strategy.

## 12. Documentation

Documentation is part of the implementation.

Update documentation when implementation changes:

- architecture;
- domain behavior;
- business rules;
- agent behavior;
- MCP contracts;
- authorization;
- security;
- data integrity;
- integration contracts;
- operational procedures.

Do not update the README speculatively. Reconcile it with verified implementation state after the issue is complete.

## 13. Scope Control

Do not perform unrelated refactoring.

Do not change dependency versions unless the issue requires it or validation proves an existing dependency/configuration is incompatible with the required implementation.

Do not change established Composer, Pint, PHPUnit, Vite, or Laravel configuration merely to make an implementation easier.

Prefer the smallest change that satisfies the issue and preserves established architecture.

## 14. Completion Criteria

An issue is complete only when:

- its implementation plan is satisfied or explicitly reconciled with the verified repository state;
- all required files are implemented;
- relevant regression coverage exists where applicable;
- local validation is clean;
- the PR contains only issue-scoped changes;
- actual GitHub Actions checks are green;
- the PR is merged;
- the issue is closed or automatically closed by the merge;
- the repository-specific Sandbox remains intact for subsequent issues.

## 15. Prohibited Shortcuts

Never:

- modify `main` directly;
- skip repository inspection;
- bypass repository development rules;
- invent validation commands that replace configured commands;
- declare CI green without checking GitHub;
- fix only a downstream CI symptom while leaving the root cause;
- close an issue while required checks are failing;
- create a new Sandbox for every issue;
- destroy the persistent repository Sandbox after an issue;
- mix unrelated changes into an issue PR.