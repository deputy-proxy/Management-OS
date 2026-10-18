# Audit logging standard

## Policy

- Audit properties are allowlist-only. Every model using `LogsActivity` declares `logOnly([...])`; fields not listed are not copied into new activity properties.
- Allowlisted changes are recorded only when changed. `dontLogEmptyChanges()` prevents a model update that changes only excluded attributes from creating an empty activity entry; event-only models deliberately use `logEmptyChanges()` to preserve the lifecycle event without attribute values. The activity event, subject, actor, and timestamp remain the accountability envelope when an approved attribute changes.
- Credentials, direct contact details, identity data, free-form content, document contents/paths, student answers, medical-leave details, and arbitrary JSON payloads are excluded unless a specific reviewed allowlist says otherwise.
- A model with `logOnly([])->logEmptyChanges()` has no model attribute allowlist but still records the lifecycle event, actor, subject, and timestamp without attribute values. This is used for identity, medical, and assessment models where the event itself is useful but the changed values are too sensitive.
- This is an operational data-minimization policy, not a claim of legal compliance. Retention periods and statutory requirements need owner/legal approval.

## Model inventory and allowlists

| Model | Logged attributes | Policy note |
| --- | --- | --- |
| `Article` | `title`, `published_in`, `scheduled_on`, `published_on`, `status` | explicit allowlist |
| `Camp` | None (event metadata only) | event-only: attributes excluded by default |
| `CampParticipant` | None (event metadata only) | event-only: attributes excluded by default |
| `Competition` | `title`, `start_on`, `end_on`, `maximum_entries` | explicit allowlist |
| `CompetitionEntry` | `competition_id`, `privacy_consented_at`, `status` | explicit allowlist |
| `Competitor` | `name`, `site`, `instagram`, `facebook`, `linkedin`, `tiktok` | explicit allowlist |
| `CompetitorOffer` | `title`, `competitor_id`, `offer_type`, `offer_price`, `last_check`, `status` | explicit allowlist |
| `ContentTemplate` | `title`, `template_for`, `link`, `last_revision` | explicit allowlist |
| `Course` | `title` | explicit allowlist |
| `CourseMaterial` | `title`, `course_id`, `added_by` | explicit allowlist |
| `Customer` | `customer_type` | explicit allowlist |
| `CustomerContract` | `contract_code`, `start_date`, `end_date`, `signed_at` | explicit allowlist |
| `CustomerInteraction` | `timestamp`, `interaction_rating` | explicit allowlist |
| `CustomerInvoice` | `amount_without_vat`, `vat`, `month`, `status` | explicit allowlist |
| `CustomerLead` | `status`, `last_contacted_at`, `converted_customer_id`, `converted_at`, `contract_sent_at`, `privacy_consented_at` | explicit allowlist |
| `Discount` | `applies_to_type`, `applies_to_item`, `value`, `type`, `start_on`, `end_on` | explicit allowlist |
| `Employee` | None (event metadata only) | event-only: attributes excluded by default |
| `EmployeeContract` | `start_date`, `end_date`, `contract_type` | explicit allowlist |
| `EmployeeEvaluation` | `due_by`, `performed_on`, `status` | explicit allowlist |
| `EmployeeEvaluationForm` | `job_description_id` | explicit allowlist |
| `EmployeeHoliday` | `start_date`, `end_date`, `hours_per_day`, `status` | explicit allowlist |
| `EmployeeMedicalLeave` | None (event metadata only) | event-only: attributes excluded by default |
| `EmployeeTimesheet` | `date`, `time_start`, `time_end`, `category`, `number_of_hours` | explicit allowlist |
| `Event` | `title`, `start_date`, `end_date`, `start_time`, `end_time` | explicit allowlist |
| `EventParticipant` | `event_id` | explicit allowlist |
| `FinancialAccount` | `title`, `type`, `bank`, `currency`, `status`, `opened_on`, `closed_on` | explicit allowlist |
| `FinancialAccountBalance` | `financial_account_id`, `current_balance`, `currency`, `reported_on` | explicit allowlist |
| `Group` | `title`, `course_id`, `teacher_id`, `status`, `initiated_on`, `formed_on`, `started_on`, `ended_on` | explicit allowlist |
| `JobDescription` | `job_title`, `job_level`, `job_role` | explicit allowlist |
| `LegalDocument` | `title`, `category`, `expiration_date` | explicit allowlist |
| `Newsletter` | `title`, `audience`, `scheduled_on`, `delivered_on`, `status` | explicit allowlist |
| `NewsletterSubscriber` | `privacy_consented_at` | explicit allowlist |
| `PaidCampaign` | `title`, `network`, `scheduled_to_start_on`, `started_on`, `scheduled_to_end_on`, `ended_on`, `budget`, `status` | explicit allowlist |
| `Partner` | `name`, `site` | explicit allowlist |
| `PotentialPartner` | `name`, `site`, `type`, `last_contact` | explicit allowlist |
| `Process` | `title`, `owner_id`, `main_topic`, `visible_to` | explicit allowlist |
| `ProductPrice` | `price`, `currency`, `priceable_type`, `priceable_id`, `price_type`, `valid_from`, `valid_until`, `legacy_source_type`, `legacy_source_id` | explicit allowlist |
| `ProductReview` | `product_type`, `product_id`, `rating`, `reviewed_on`, `privacy_consented_at`, `status` | explicit allowlist |
| `ProgressUpdate` | `update_date` | explicit allowlist |
| `Project` | `title`, `status`, `planned_start_date`, `planned_end_date`, `start_date`, `end_date`, `budget` | explicit allowlist |
| `ProjectPhase` | `title`, `project_id`, `start_on`, `end_on`, `order` | explicit allowlist |
| `QuarterlyObjective` | `employee_id`, `quarter`, `status` | explicit allowlist |
| `Session` | `session_type`, `group_id`, `course_id`, `starts_at`, `ends_at`, `status`, `attendance_status`, `identifier` | explicit allowlist |
| `SessionAssistance` | `session_id`, `assistance_date`, `status` | explicit allowlist |
| `SessionAttendance` | `session_id`, `status`, `achievement_badge` | explicit allowlist |
| `SocialPost` | `title`, `network`, `scheduled_on`, `published_on`, `status` | explicit allowlist |
| `Student` | None (event metadata only) | event-only: attributes excluded by default |
| `Task` | `assigned_to`, `project_id`, `project_phase_id`, `status`, `due_date`, `taskable_id`, `taskable_type`, `created_by` | explicit allowlist |
| `Tax` | `category`, `month`, `amount`, `status`, `due_on`, `paid_on` | explicit allowlist |
| `Test` | `title`, `topic`, `level`, `created_by`, `test_type_id`, `time_limit`, `passing_score` | explicit allowlist |
| `TestQuestion` | `question_type` | explicit allowlist |
| `TestResult` | None (event metadata only) | event-only: attributes excluded by default |
| `TestSimulation` | `test_id`, `title`, `date`, `number_of_hours`, `status` | explicit allowlist |
| `TestSubmission` | `test_id`, `submission_timestamp`, `status`, `score` | explicit allowlist |
| `TestType` | `title` | explicit allowlist |
| `Transaction` | `financial_account_id`, `vendor_invoice_id`, `transaction_type`, `amount`, `payment_method`, `timestamp`, `customer_invoice_id`, `tax_id` | explicit allowlist |
| `User` | None (event metadata only) | event-only: attributes excluded by default |
| `Vendor` | `name`, `vendor_category_id`, `start_date`, `end_date`, `website` | explicit allowlist |
| `VendorCategory` | `title` | explicit allowlist |
| `VendorContract` | `start_date`, `end_date`, `vendor_id`, `contract_code` | explicit allowlist |
| `VendorInvoice` | `vendor_id`, `vendor_contract_id`, `amount_without_vat`, `vat`, `currency`, `month`, `status`, `amount`, `invoice_number`, `identifier` | explicit allowlist |
| `Workshop` | `title`, `start_date`, `end_date`, `start_time`, `end_time`, `type` | explicit allowlist |
| `WorkshopMaterial` | `title`, `workshop_id`, `added_by` | explicit allowlist |
| `WorkshopParticipant` | `workshop_id` | explicit allowlist |

## Request context

Request IP address and user-agent metadata are added by the configured `SetActivityContextTap` in `config/activitylog.php`. The additional `Activity::creating` hook in `AppServiceProvider` was removed because it duplicated that responsibility. IP address and user-agent are personal/technical data; capture them only for the documented security purpose and restrict access to audit records.

## Access and mutation controls

The Activity Log resource is view-only. Listing and viewing activities require the explicit `activity-log.index` role permission. Create, update, revert, restore, delete, prune, and bulk-delete operations are disabled. Export remains available only to users authorized to view the log; exported files must be handled as sensitive operational records. No activity-log API serialization route was found in the current application routes; API access remains a separate boundary and must be reviewed if such an endpoint is added.

## Retention and historical records

`config/activitylog.php` currently sets `clean_after_days` to 365. This is the age used by the package cleanup command; configuration alone does not prove that cleanup is scheduled or routinely executed. Verify the deployed scheduler/operational runbook before claiming automated deletion. New policy changes apply to future activity entries only. Existing historical activity rows are not rewritten or deleted by this change; any historical redaction or deletion requires a separately approved retention/legal decision and a tested backup/recovery plan.

## Adding logging to a model

1. Inventory the model fields and their sensitivity, including casts, relationships, free-form inputs, and uploaded-file paths.
2. Start with `logOnly([])` and add only fields required for an identified audit purpose.
3. Never log passwords, tokens, secrets, direct identifiers, medical details, free-form document/content fields, or arbitrary JSON without a documented approved need.
4. Use `logOnlyDirty()->dontLogEmptyChanges()` so only reviewed changes are stored and empty changes do not create noise. For models where the lifecycle event is important but all values are sensitive, use `logOnly([])->logEmptyChanges()` instead.
5. Add a regression test proving disallowed values are absent from `properties` and allowed state changes remain traceable to actor and timestamp.
6. Update this inventory and obtain policy-owner approval for any new sensitive-field exception.
