# Authorization and Filament panel access

## Panel admission

The role name configured by `HEXA_SUPER_ADMIN_ROLE` (default: `Owner`) is the explicit superadministrator role. It can enter the panel and bypass individual HexaLite permission keys, including newly added resource permissions. Keep this role assigned only to trusted system owners. Role-name matching is case-insensitive.

The `/admin` Filament panel uses the application's existing HexaLite role/permission model. `App\Models\User::canAccessPanel()` denies access unless the account has at least one assigned `web` role containing at least one non-empty permission. Authentication alone is not sufficient. A role with an empty permission set is not an access grant.

This explicit admission check is important because the bundled HexaLite helper treats users without roles as superadmins when evaluating individual permissions. That helper behavior must not be used as the panel-admission rule.

Before deploying this change, inventory the existing production user-role assignments. Any currently authorized staff account without a role, or with a role whose permission map is empty, will be denied panel access until its access is explicitly provisioned. Do not solve this by adding a blanket fallback or automatically granting administrator permissions.

## Provisioning an administrator

1. Sign in with an already authorized account and open **Role & Permissions**.
2. Create or update a role and grant only the permissions required for the staff member's duties. For an administrator who must manage permissions, grant the `role.index`, `role.create`, `role.update`, and `role.delete` permissions, along with the specific operational permissions they need.
3. Open the Users resource and assign that role to the staff account.
4. Verify access with the target account and confirm that resources outside its permission set are denied.

Do not make an account an administrator merely by changing its email address or domain. The application has no verified email-domain allowlist for panel access.

### Emergency recovery

If all administrators are locked out, use an approved database backup and the application's configured environment to run `php artisan tinker` on the trusted application host. Identify the intended existing user, create a HexaLite role with a minimal, reviewed `access` map, then attach it to that user through `User::roles()`. For example, the following grants permission administration plus access to the user list; add operational permissions only after review:

```php
$user = App\Models\User::where('email', 'admin@example.com')->firstOrFail();
$roleClass = config('hexa.models.role');
$role = $roleClass::create([
    'name' => 'Emergency Administrator',
    'created_by_name' => 'Approved recovery',
    'guard' => 'web',
    'access' => [
        'Role & Permissions' => ['role.index', 'role.create', 'role.update', 'role.delete'],
        'Users' => ['user.index'],
    ],
]);
$user->roles()->syncWithoutDetaching([$role->getKey()]);
```

Use the actual account email and validate the permission keys against the currently registered resources/pages before running the command. Record the recovery in the organization's change log, rotate any credentials involved in the incident, and remove the emergency role or reduce it to normal duties after access is restored. Never commit real account identifiers or production data to the repository.

## Permission matrix

HexaLite roles are configurable permission bundles, not a fixed set of hard-coded business roles. Access is granted by explicit permission keys registered by Filament resources and pages. Assignments should follow least privilege.

| Domain | Example permission keys | Boundary to verify |
| --- | --- | --- |
| Students and academic operations | `student.index`, `group.index`, `session.index`, `test.index` | Student, group, session, attendance, assessment, and progress records |
| Employees and HR | `employee.index`, `employee_contract.index`, `employee_medical_leafe.index`, `employee_pay_slip.index` | Employee personal data, contracts, medical leave, evaluations, and payroll |
| Finance and customer billing | `customer_invoice.index`, `vendor_invoice.index`, `transaction.index`, `financial_account.index` | Invoices, transactions, balances, and financial records |
| CRM | `customer.index`, `customer_lead.index`, `customer_contract.index` | Customer records, lead conversion, contracts, and interactions |
| Products and public submissions | `product.index`, `product_review.index`, `competition_entry.index` | Product data, moderation queues, and submitted personal data |
| Projects and internal work | `task.index`, `project.index`, `project_phase.index` | Assigned-task scoping and restricted project information |
| System administration | `user.index`, `role.index`, `auth-log.index` | User management, role assignment, authentication logs, and audit logs |
| Custom dashboards | `overview-dashboard.view`, `finance-dashboard.view`, `students-dashboard.view`, `teachers-dashboard.view`, `content-management.view` | Page admission plus permission-filtered metrics and links |

These are examples of registered permission keys, not a recommendation to grant every key to one role. Confirm actual keys in the running Role & Permissions UI because the set is derived from registered resources and pages.

## Authorization review checklist

- Panel admission requires a role with an explicit permission; a logged-in account with no role is denied.
- Resource `canAccess()` checks and custom page `canAccess()` checks are server-side authorization, not just navigation visibility.
- Custom record actions must use both appropriate visibility rules and server-side authorization in the action handler. Do not rely on hidden buttons alone.
- The shared `TasksAction` requires `task.index` to inspect related tasks and uses `TaskResource` query scoping for task visibility. Creating a task requires `task.create`; assigning it to another user additionally requires `task.assign`.
- The invoice-linked product revenue and product resource actions require the relevant `*.index` permission to view an existing linked record and the relevant `*.create` permission to create one. Their handlers repeat the create authorization check.
- Customer lead conversion and contract delivery use both visibility and authorization checks; session attendance and feedback actions re-check teacher ownership and permission in the action handler.
- Dashboard queries must apply the same permission boundaries as their linked resources. Counts, recent activity, relationship labels, calendar events, and search results can disclose restricted records even when links are hidden.
- `NotificationSettings` and `Profile` are self-service pages. Their data operations must remain scoped to the authenticated user.
- Public website submission routes are intentionally unauthenticated and must be reviewed separately for validation, throttling, consent, and safe error handling; Filament authorization does not protect them.
- Sanctum API routes are a separate boundary. Admin panel admission does not authorize API requests.
- When adding a resource, page, widget, export, or custom route, add its permission checks and positive/negative regression tests as part of the same change.

## Verification

The `PanelAccessAuthorizationTest` suite verifies no-role denial, empty-role denial, explicit-role admission, and denial of the admin route to an authenticated user without a role. Resource-specific tests remain responsible for direct URL, record-ID, action, and sensitive-data access checks.
