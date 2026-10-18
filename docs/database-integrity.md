# Database integrity catalogue

This catalogue records the enforcement boundary for critical business invariants. `php artisan database:integrity-audit` is a read-only diagnostic command: it reports sample duplicate groups and orphan references, returns a failing exit code when configured findings exist, and never modifies data. Run it against a production-like database before constraint migrations and retain its output with release evidence.

## Invariant matrix

| Domain / invariant | Current enforcement | Database backstop | Diagnostics / tests | Policy and limitations |
| --- | --- | --- | --- | --- |
| Products: one canonical product per productable | Product sync service and observer | Unique `(productable_type, productable_id)` from the existing migration | `database:integrity-audit`; `ProductableObserverTest` | Polymorphic target cannot be a conventional foreign key. Existing duplicate products cause migration failure; inspect relationships before reconciliation. |
| Reviews: one active review per customer/product | Public submission validation | Unique generated active-customer key and customer FK | `PublicFormsTest`; integrity audit | Soft-deleted review history remains; replacement is allowed. Product target is polymorphic. |
| Leads: converted status requires customer and timestamp | `CustomerLead` model lifecycle checks | `converted_customer_id` FK; no portable cross-column CHECK currently | `CustomerLeadLifecycleTest`; integrity audit | Direct SQL can bypass model lifecycle checks. Converted historical rows with missing metadata are normalized by an earlier migration, so preserve the migration history. |
| Contracts: one source lead per customer contract | Conversion workflow | Unique `source_customer_lead_id` and FK from existing migration | Integrity audit | Source lead FK is configured `nullOnDelete`; historical contract remains but its source link may become null. |
| Payroll: one payslip per employee/month | `EmployeePaySlip` lifecycle and monthly generation workflow | Unique `(employee_id, month)` | `EmployeePaySlipWorkflowTest`; integrity audit | Month normalization and duplicate preflight exist in the earlier monthly workflow migration. Rollback must not discard financial values. |
| Payroll: source item uniqueness and immutability | `EmployeePaySlipItem` lifecycle | Unique `(payslip, source_type, source_id)`, unique `source_key`, parent FK with restrict-on-delete | Payroll workflow tests; integrity audit | Polymorphic source has no FK; source snapshot is immutable and rollback refuses to drop populated history. |
| Competition: unique entry email per competition | Shared submission service, transactional parent-row lock for capacity | Unique `(competition_id, email)` | `PublicFormsTest`; integrity audit | Count-based capacity is transaction/locking-dependent and must be verified on production MySQL/InnoDB. |
| Attendance: one active row per session/student | Existing attendance workflows update the row rather than creating duplicates | New unique generated active-student key; FKs to scheduled session and student | `DatabaseIntegrityAuditTest`; integrity audit | Soft-deleted attendance can be replaced while retaining history. Migration aborts on active duplicates or orphan references. |
| Customer invoices: customer and contract references | Invoice workflows and model relationships | Legacy columns currently lack explicit DB FKs in the inspected migrations | Integrity audit | This issue deliberately does not add financial FKs without running the read-only audit and reconciling production orphans. |
| Vendor invoices: vendor and contract references | Invoice workflows and model relationships | Legacy columns currently lack explicit DB FKs in the inspected migrations | Integrity audit | Requires production preflight and review of legacy rows before adding constraints. |
| Product revenue: product and invoice references | Finance workflows | Legacy columns currently lack explicit DB FKs in the inspected migrations | Integrity audit | Financial rows are not deleted or rewritten by this issue. |
| Transactions: financial account and invoice/tax references | Transaction workflows | Existing columns are mostly not declared as DB foreign keys in inspected migrations | Manual review; production schema audit required | `customer_invoice_id` and `tax_id` were added as required columns by a legacy migration. Do not add constraints until type semantics and historical references are confirmed. |

## New attendance constraint migration

`2026_10_18_200000_enforce_session_attendance_integrity.php` checks active duplicates and orphan session/student IDs before changing the schema. It then adds a virtual `active_student_id` that is `student_id` only for active rows and `NULL` for soft-deleted rows, a unique `(session_id, active_student_id)` index, and restrictive foreign keys. It fails with an actionable error rather than dropping or merging attendance history. Rollback removes only the newly added indexes, column, and foreign keys; it does not alter attendance records.

## Production rollout procedure

1. Take and verify a restorable database backup.
2. Run `php artisan database:integrity-audit` with read-only credentials first; save its output.
3. Reconcile each reported duplicate/orphan with a documented, approved data decision. Never automatically delete or merge invoices, transactions, payslips, source items, attendance, reviews, or audit history.
4. Run the migration on a production-version staging clone and verify the generated column, unique index, and foreign keys with the engine's schema inspection commands.
5. Exercise concurrent attendance creation and competition entry capacity using separate database connections on the actual MySQL/InnoDB version.
6. Keep the migration rollback plan and database backup available. Rollback of the attendance migration drops only the new constraints, not business records.

The development Sandbox is SQLite-based and has no production MySQL connection. SQLite tests verify migration compatibility and basic constraint behavior, not MySQL DDL locking, generated-column indexing differences, or InnoDB concurrency. This verification remains a release prerequisite.
