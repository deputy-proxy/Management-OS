# Navigation resource badge inventory

The Filament resource inventory contains 82 resource classes. Badges are reserved for actionable queues or time-sensitive records; a raw record total is not automatically actionable. Counts are uncached so navigation reflects current records. A badge must be suppressed when the current user cannot view that resource.

## Existing badges reviewed

| Resource | Displayed value | Scope and empty behavior |
|---|---|---|
| `app/Filament/Resources/Articles/ArticleResource.php` | Total articles | Inventory count; no actionable status semantics were defined in the resource. |
| `app/Filament/Resources/CustomerLeads/CustomerLeadResource.php` | See resource implementation | Permission and empty behavior require review. |
| `app/Filament/Resources/DigitalAssets/DigitalAssetResource.php` | Total digital assets | Inventory count, not an actionable queue. |
| `app/Filament/Resources/EmployeeContracts/EmployeeContractResource.php` | Contracts ending within 3 months | Requires contract list access; urgency color reflects those ending within 1 month; zero renders no badge. |
| `app/Filament/Resources/EmployeeEvaluationForms/EmployeeEvaluationFormResource.php` | Total evaluation forms | Inventory count, not an actionable queue; zero behavior follows resource implementation. |
| `app/Filament/Resources/EmployeeEvaluations/EmployeeEvaluationResource.php` | Evaluations in progress | Requires evaluation list access; zero renders no badge. |
| `app/Filament/Resources/EmployeeHolidays/EmployeeHolidayResource.php` | Pending holiday requests | Requires holiday list access; zero renders no badge. |
| `app/Filament/Resources/EmployeeMedicalLeaves/EmployeeMedicalLeaveResource.php` | Medical leaves active today | Requires medical-leave list access; zero renders no badge. |
| `app/Filament/Resources/EmployeePaySlips/EmployeePaySlipResource.php` | Open payslips (`status = Open`) | Requires payslip list access; zero renders no badge. |
| `app/Filament/Resources/Employees/EmployeeResource.php` | Total employees | Only shown to users with employee list access; zero renders no badge after this change is applied. |
| `app/Filament/Resources/LegalDocuments/LegalDocumentResource.php` | Legal documents expiring within 3 months | Requires document list access; urgency color reflects those expiring within 1 month; zero renders no badge. |
| `app/Filament/Resources/PhysicalAssets/PhysicalAssetResource.php` | Total physical assets | Inventory count, not an actionable queue. |
| `app/Filament/Resources/Processes/ProcessResource.php` | Total defined processes | Inventory count, not an actionable queue. |
| `app/Filament/Resources/Tasks/TaskResource.php` | See resource implementation | Permission and empty behavior require review. |
| `app/Filament/Resources/TestSubmissions/TestSubmissionResource.php` | See resource implementation | Permission and empty behavior require review. |

## Actionable badges added in this change

| Resource | Meaning | Scope and empty behavior |
|---|---|---|
| `app/Filament/Resources/CustomerLeads/CustomerLeadResource.php` | Leads with `converted_at IS NULL` | Only with `customer_lead.index`; zero renders no badge. This measures unconverted leads, not a guessed status value. |
| `app/Filament/Resources/Tasks/TaskResource.php` | Tasks whose status is not `done` | Requires `task.index`; users with `task.view_all` see all non-deleted tasks, others see only tasks assigned to them or created by them. Zero renders no badge. |
| `app/Filament/Resources/TestSubmissions/TestSubmissionResource.php` | Submissions with status `submitted` or `grading` | Only with `test_submission.index`; zero renders no badge. |

## Resources without badges

The following resources have no badge because their current models/resources do not establish an agreed actionable count or status. This avoids presenting arbitrary totals as work that needs attention. Revisit only when a concrete queue/status definition is agreed.

- `app/Filament/Resources/AuthenticationLogResource.php`
- `app/Filament/Resources/CampParticipants/CampParticipantResource.php`
- `app/Filament/Resources/Camps/CampResource.php`
- `app/Filament/Resources/CompetitionEntries/CompetitionEntryResource.php`
- `app/Filament/Resources/Competitions/CompetitionResource.php`
- `app/Filament/Resources/CompetitorOffers/CompetitorOfferResource.php`
- `app/Filament/Resources/Competitors/CompetitorResource.php`
- `app/Filament/Resources/CourseMaterials/CourseMaterialResource.php`
- `app/Filament/Resources/Courses/CourseResource.php`
- `app/Filament/Resources/CustomerContracts/CustomerContractResource.php`
- `app/Filament/Resources/CustomerInteractions/CustomerInteractionResource.php`
- `app/Filament/Resources/CustomerInvoices/CustomerInvoiceResource.php`
- `app/Filament/Resources/Customers/CustomerResource.php`
- `app/Filament/Resources/Discounts/DiscountResource.php`
- `app/Filament/Resources/EmployeeTimesheets/EmployeeTimesheetResource.php`
- `app/Filament/Resources/EventParticipants/EventParticipantResource.php`
- `app/Filament/Resources/Events/EventResource.php`
- `app/Filament/Resources/FinancialAccountBalances/FinancialAccountBalanceResource.php`
- `app/Filament/Resources/FinancialAccounts/FinancialAccountResource.php`
- `app/Filament/Resources/Sessions/SessionResource.php`
- `app/Filament/Resources/Groups/GroupResource.php`
- `app/Filament/Resources/JobDescriptions/JobDescriptionResource.php`
- `app/Filament/Resources/MeetingTopics/MeetingTopicResource.php`
- `app/Filament/Resources/Meetings/MeetingResource.php`
- `app/Filament/Resources/NewsletterSubscribers/NewsletterSubscriberResource.php`
- `app/Filament/Resources/Newsletters/NewsletterResource.php`
- `app/Filament/Resources/PaidCampaigns/PaidCampaignResource.php`
- `app/Filament/Resources/Partners/PartnerResource.php`
- `app/Filament/Resources/PotentialPartners/PotentialPartnerResource.php`
- `app/Filament/Resources/ProductCosts/ProductCostResource.php`
- `app/Filament/Resources/ProductPrices/ProductPriceResource.php`
- `app/Filament/Resources/ProductResourceAllocations/ProductResourceAllocationResource.php`
- `app/Filament/Resources/ProductResources/Pages/CreateProductResource.php`
- `app/Filament/Resources/ProductResources/Pages/EditProductResource.php`
- `app/Filament/Resources/ProductResources/Pages/ViewProductResource.php`
- `app/Filament/Resources/ProductResources/ProductResourceResource.php`
- `app/Filament/Resources/ProductRevenues/ProductRevenueResource.php`
- `app/Filament/Resources/ProductReviews/ProductReviewResource.php`
- `app/Filament/Resources/Products/ProductResource.php`
- `app/Filament/Resources/ProgressUpdates/ProgressUpdateResource.php`
- `app/Filament/Resources/ProjectPhases/ProjectPhaseResource.php`
- `app/Filament/Resources/Projects/ProjectResource.php`
- `app/Filament/Resources/QuarterlyObjectives/QuarterlyObjectiveResource.php`
- `app/Filament/Resources/SessionAssistances/SessionAssistanceResource.php`
- `app/Filament/Resources/SessionAttendances/SessionAttendanceResource.php`
- `app/Filament/Resources/SocialPosts/SocialPostResource.php`
- `app/Filament/Resources/Students/StudentResource.php`
- `app/Filament/Resources/Taxes/TaxResource.php`
- `app/Filament/Resources/TestQuestions/TestQuestionResource.php`
- `app/Filament/Resources/TestResults/TestResultResource.php`
- `app/Filament/Resources/TestSubmissionAnswers/TestSubmissionAnswerResource.php`
- `app/Filament/Resources/TestTypes/TestTypeResource.php`
- `app/Filament/Resources/Tests/TestResource.php`
- `app/Filament/Resources/Transactions/TransactionResource.php`
- `app/Filament/Resources/Users/UserResource.php`
- `app/Filament/Resources/VendorCategories/VendorCategoryResource.php`
- `app/Filament/Resources/VendorContracts/VendorContractResource.php`
- `app/Filament/Resources/VendorInvoices/VendorInvoiceResource.php`
- `app/Filament/Resources/Vendors/VendorResource.php`
- `app/Filament/Resources/WorkshopMaterials/WorkshopMaterialResource.php`
- `app/Filament/Resources/WorkshopParticipants/WorkshopParticipantResource.php`
- `app/Filament/Resources/Workshops/WorkshopResource.php`