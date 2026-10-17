# Filament resource edit-page template

## Scope and migration status

The shared template is introduced through `App\Filament\Resources\Pages\EditRecord`, which extends Filament 5's `EditRecord` rather than replacing its form lifecycle. `Articles` is the representative migrated resource. Its header actions use the shared delete action and append the existing Comments and Tasks actions. The `Users` page remains a deliberate exception for now because it also exposes a View action and has not yet been migrated.

There are 78 resource edit pages at the time of this audit. The remaining migrations are listed below; migrate incrementally and preserve resource-specific actions and form schemas.

## Page template

- **Breadcrumbs and title:** use Filament's resource/page defaults unless the resource has a meaningful domain-specific label.
- **Form content:** keep fields in the resource's existing `Schemas/*Form.php` class. Use schema sections/tabs for meaningful groups, not merely to make every form identical. Preserve custom column definitions; otherwise Filament's edit page uses its standard responsive two-column layout (one column for inline labels).
- **Validation:** field-level rules remain in the resource form schema. Server-side validation is authoritative; failed validation must keep the user on the page and retain submitted state/errors.
- **Save:** retain Filament's built-in Save action and keyboard shortcut. Use the default saved notification and redirect unless the workflow has a documented reason to differ.
- **Cancel:** retain Filament's built-in Cancel action, which returns to the previous URL when available and otherwise to the resource URL.
- **Unsaved changes:** retain Filament's built-in `HasUnsavedDataChangesAlert` behavior. Do not add a second custom warning without a demonstrated gap.
- **Header actions:** shared `EditRecord` supplies the standard Delete action. A resource may override `getHeaderActions()` and append custom actions using `...parent::getHeaderActions()`. Pages that intentionally replace deletion or add a View action must document that exception.
- **Relationships:** keep relation managers and relationship-aware form components on the resource. Editing scalar fields must not detach or overwrite relationships unless the user explicitly changes those fields.
- **Authorization:** Filament's edit page authorizes access on mount, hydration, and save through `Resource::canEdit()`. Resource-specific authorization responses/policies remain the source of truth. Hiding a button is not authorization.
- **Responsive behavior:** use Filament schema layout primitives and their breakpoints; do not introduce a custom page view just to duplicate built-in layout behavior.

## Extension points

1. Keep `Resource::form()` as the source of the form schema.
2. Keep resource-specific actions in the resource's `Pages/Edit*.php` class.
3. Override lifecycle hooks (`beforeValidate`, `afterValidate`, `beforeSave`, `afterSave`, or form-data mutation hooks) only for domain behavior that cannot live in a form schema/service.
4. Use relationship components/relation managers for related records and test that unrelated relationships survive an update.
5. Add tests for authorization denial, validation errors, successful persistence, and relationship preservation for each migrated resource where applicable.

## Migration checklist

- [x] Articles (representative implementation)
- [ ] CampParticipants
- [ ] Camps
- [ ] CompetitionEntries
- [ ] Competitions
- [ ] CompetitorOffers
- [ ] Competitors
- [ ] ContentTemplates
- [ ] CourseMaterials
- [ ] CoursePrices
- [ ] Courses
- [ ] CustomerContracts
- [ ] CustomerInteractions
- [ ] CustomerInvoices
- [ ] CustomerLeads
- [ ] Customers
- [ ] DigitalAssets
- [ ] Discounts
- [ ] EmployeeContracts
- [ ] EmployeeEvaluationForms
- [ ] EmployeeEvaluations
- [ ] EmployeeHolidays
- [ ] EmployeeMedicalLeaves
- [ ] EmployeePaySlips
- [ ] EmployeeTimesheets
- [ ] Employees
- [ ] EventParticipants
- [ ] EventPrices
- [ ] Events
- [ ] FinancialAccountBalances
- [ ] FinancialAccounts
- [ ] GroupSessions
- [ ] Groups
- [ ] IndividualSessions
- [ ] JobDescriptions
- [ ] LegalDocuments
- [ ] MeetingTopics
- [ ] Meetings
- [ ] NewsletterSubscribers
- [ ] Newsletters
- [ ] PaidCampaigns
- [ ] Partners
- [ ] PhysicalAssets
- [ ] PotentialPartners
- [ ] Processes
- [ ] ProductCosts
- [ ] ProductPrices
- [ ] ProductResourceAllocations
- [ ] ProductResources
- [ ] ProductRevenues
- [ ] ProductReviews
- [ ] Products
- [ ] ProgressUpdates
- [ ] ProjectPhases
- [ ] Projects
- [ ] QuarterlyObjectives
- [ ] SessionAssistances
- [ ] SessionAttendances
- [ ] SocialPosts
- [ ] Students
- [ ] Tasks
- [ ] Taxes
- [ ] TestQuestions
- [ ] TestResults
- [ ] TestSubmissionAnswers
- [ ] TestSubmissions
- [ ] TestTypes
- [ ] Tests
- [ ] Transactions
- [ ] VendorCategories
- [ ] VendorContracts
- [ ] VendorInvoices
- [ ] Vendors
- [ ] WorkshopMaterials
- [ ] WorkshopParticipants
- [ ] WorkshopPrices
- [ ] Workshops
- [ ] Users (exception: includes View action; migrate after confirming desired action order)
