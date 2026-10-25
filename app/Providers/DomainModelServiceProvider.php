<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\BankStatementImportItem;
use App\Models\Camp;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Competitor;
use App\Models\CompetitorOffer;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\CustomerLead;
use App\Models\Discount;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationForm;
use App\Models\EmployeeHoliday;
use App\Models\EmployeeMedicalLeave;
use App\Models\EmployeeSalary;
use App\Models\EmployeeTimesheet;
use App\Models\Event as EventModel;
use App\Models\EventParticipant;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountBalance;
use App\Models\Group;
use App\Models\IssuedInvoice;
use App\Models\JobDescription;
use App\Models\LegalDocument;
use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use App\Models\PaidCampaign;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\PotentialPartner;
use App\Models\Process;
use App\Models\ProductPrice;
use App\Models\ProductResource;
use App\Models\ProductResourceAllocation;
use App\Models\ProductReview;
use App\Models\ProgressUpdate;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\QuarterlyObjective;
use App\Models\Session;
use App\Models\SessionAssistance;
use App\Models\SessionAttendance;
use App\Models\SocialPost;
use App\Models\Student;
use App\Models\Task;
use App\Models\Tax;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\TestResult;
use App\Models\TestSimulation;
use App\Models\TestSubmission;
use App\Models\TestType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\VendorContract;
use App\Models\VendorInvoice;
use App\Models\Workshop;
use App\Models\WorkshopMaterial;
use App\Models\WorkshopParticipant;
use App\Observers\ProductableObserver;
use App\Observers\ProductResourceAllocationObserver;
use App\Observers\ProductResourceObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class DomainModelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ProductResource::observe(ProductResourceObserver::class);
        foreach ([Camp::class, Course::class, EventModel::class, Workshop::class] as $productableModel) {
            $productableModel::observe(ProductableObserver::class);
        }
        ProductResourceAllocation::observe(ProductResourceAllocationObserver::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'course' => Course::class,
            'camp' => Camp::class,
            'product_price' => ProductPrice::class,
            'course_material' => CourseMaterial::class,
            'course_price' => ProductPrice::class,
            'article' => Article::class,
            'employee' => Employee::class,
            'employee_evaluation_form' => EmployeeEvaluationForm::class,
            'employee_holiday' => EmployeeHoliday::class,
            'employee_evaluation' => EmployeeEvaluation::class,
            'employee_salary' => EmployeeSalary::class,
            'employee_contract' => EmployeeContract::class,
            'employee_medical_leave' => EmployeeMedicalLeave::class,
            'employee_timesheet' => EmployeeTimesheet::class,
            'job_description' => JobDescription::class,
            'competition' => Competition::class,
            'competition_entry' => CompetitionEntry::class,
            'customer' => Customer::class,
            'customer_contract' => CustomerContract::class,
            'customer_interaction' => CustomerInteraction::class,
            'customer_invoice' => CustomerInvoice::class,
            'issued_invoice' => IssuedInvoice::class,
            'customer_lead' => CustomerLead::class,
            'financial_account' => FinancialAccount::class,
            'financial_account_balance' => FinancialAccountBalance::class,
            'tax' => Tax::class,
            'transaction' => Transaction::class,
            'bank_statement_import_item' => BankStatementImportItem::class,
            'payment_allocation' => PaymentAllocation::class,
            'discount' => Discount::class,
            'competitor' => Competitor::class,
            'competitor_offer' => CompetitorOffer::class,
            'event' => EventModel::class,
            'event_participant' => EventParticipant::class,
            'event_price' => ProductPrice::class,
            'group' => Group::class,
            'session' => Session::class,
            'test_simulation' => TestSimulation::class,
            'process' => Process::class,
            'legal_document' => LegalDocument::class,
            'project' => Project::class,
            'project_phase' => ProjectPhase::class,
            'task' => Task::class,
            'partner' => Partner::class,
            'potential_partner' => PotentialPartner::class,
            'student' => Student::class,
            'progress_update' => ProgressUpdate::class,
            'session_assistance' => SessionAssistance::class,
            'session_attendance' => SessionAttendance::class,
            'newsletter' => Newsletter::class,
            'newsletter_subscriber' => NewsletterSubscriber::class,
            'product_review' => ProductReview::class,
            'quarterly_objective' => QuarterlyObjective::class,
            'vendor' => Vendor::class,
            'vendor_category' => VendorCategory::class,
            'vendor_contract' => VendorContract::class,
            'vendor_invoice' => VendorInvoice::class,
            'workshop_participant' => WorkshopParticipant::class,
            'workshop_price' => ProductPrice::class,
            'workshop_material' => WorkshopMaterial::class,
            'workshop' => Workshop::class,
            'social_post' => SocialPost::class,
            'paid_campaign' => PaidCampaign::class,
            'test' => Test::class,
            'test_question' => TestQuestion::class,
            'test_submission' => TestSubmission::class,
            'test_result' => TestResult::class,
            'test_type' => TestType::class,
        ]);
    }
}
