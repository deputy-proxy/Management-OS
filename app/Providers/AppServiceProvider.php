<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Relaticle\Comments\Events\CommentCreated; // Ensure this matches your package
use Relaticle\Comments\Models\Subscription;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Filament\Actions\Action;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Relation::enforceMorphMap([
            'user' => \App\Models\User::class,

            'course' => \App\Models\Course::class,
            'course_material' => \App\Models\CourseMaterial::class,
            'course_price' => \App\Models\CoursePrice::class,

            'article' => \App\Models\Article::class,
            
            'employee' => \App\Models\Employee::class,
            'employee_evaluation_form' => \App\Models\EmployeeEvaluationForm::class,
            'employee_holiday' => \App\Models\EmployeeHoliday::class,
            'employee_evaluation' => \App\Models\EmployeeEvaluation::class,
            'employee_salary' => \App\Models\EmployeeSalary::class,
            'employee_contract' => \App\Models\EmployeeContract::class,
            'employee_medical_leave' => \App\Models\EmployeeMedicalLeave::class,
            'employee_timesheet' => \App\Models\EmployeeTimesheet::class,

            'job_description' => \App\Models\JobDescription::class,

            'competition' => \App\Models\Competition::class,
            'competition_entry' => \App\Models\CompetitionEntry::class,
            'content_template' => \App\Models\ContentTemplate::class,

            'customer' => \App\Models\Customer::class,
            'customer_contract' => \App\Models\CustomerContract::class,
            'customer_interaction' => \App\Models\CustomerInteraction::class,
            'customer_invoice' => \App\Models\CustomerInvoice::class,
            'customer_lead' => \App\Models\CustomerLead::class,
            
            'financial_account' => \App\Models\FinancialAccount::class,
            'financial_account_balance' => \App\Models\FinancialAccountBalance::class,

            'tax' => \App\Models\Tax::class,
            'transaction' => \App\Models\Transaction::class,
            
            'discount' => \App\Models\Discount::class,
            
            'competitor' => \App\Models\Competitor::class,
            'competitor_offer' => \App\Models\CompetitorOffer::class,
            
            'event' => \App\Models\Event::class,
            'event_participant' => \App\Models\EventParticipant::class,
            'event_price' => \App\Models\EventPrice::class,
            
            'group' => \App\Models\Group::class,
            'group_session' => \App\Models\GroupSession::class,
            
            'process' => \App\Models\Process::class,

            'project' => \App\Models\Project::class,
            'project_phase' => \App\Models\ProjectPhase::class,

            'task' => \App\Models\Task::class,

            'partner' => \App\Models\Partner::class,
            'potential_partner' => \App\Models\PotentialPartner::class,

            'student' => \App\Models\Student::class,
            'progress_update' => \App\Models\ProgressUpdate::class,

            'session_assistance' => \App\Models\SessionAssistance::class,
            'session_attendance' => \App\Models\SessionAttendance::class,

            'newsletter' => \App\Models\Newsletter::class,
            'newsletter_subscriber' => \App\Models\NewsletterSubscriber::class,

            'product_review' => \App\Models\ProductReview::class,
            'quarterly_objective' => \App\Models\QuarterlyObjective::class,

            'vendor' => \App\Models\Vendor::class,
            'vendor_category' => \App\Models\VendorCategory::class,
            'vendor_contract' => \App\Models\VendorContract::class,
            'vendor_invoice' => \App\Models\VendorInvoice::class,

            'workshop_participant' => \App\Models\WorkshopParticipant::class,
            'workshop_price' => \App\Models\WorkshopPrice::class,
            'workshop_material' => \App\Models\WorkshopMaterial::class,
            'workshop' => \App\Models\Workshop::class,
            
            'social_post' => \App\Models\SocialPost::class,
            'paid_campaign' => \App\Models\PaidCampaign::class,
        ]);

Event::listen(CommentCreated::class, function (CommentCreated $event) {
            $commentable = $event->commentable;
            $comment = $event->comment;
            $title = 'New comment on '.class_basename($commentable).': '.$event->commentable->title; ;
            $body = $comment->body;
            $owner = null;
            foreach (['owner', 'user', 'author', 'createdBy'] as $relation) {
                if (method_exists($commentable, $relation)) {
                    $candidate = $commentable->{$relation};
                    if ($candidate instanceof Model) {
                        $owner = $candidate;
                        break;
                    }
                }
            }
            $subscribers = Subscription::subscribersFor($commentable)
                ->filter(fn ($subscriber) => $subscriber instanceof Model)
                ->values();
            $recipients = collect()
                ->when($owner instanceof Model, fn ($collection) => $collection->push($owner))
                ->merge($subscribers)
                ->unique(fn ($recipient) => $recipient->getMorphClass().':'.$recipient->getKey())
                ->filter(function ($recipient) use ($comment) {
                    return ! ($recipient instanceof Model && method_exists($recipient, 'is') && $comment->commenter instanceof Model && $recipient->is($comment->commenter));
                })
                ->values();

            if ($recipients->isEmpty()) {
                return;
            }
            foreach ($recipients as $recipient) {
                Notification::make()
                    ->title($title)
                    ->success()
                    ->body($body)
                    ->sendToDatabase($recipient)
                    ->broadcast($recipient);
            }
        });
    }
}
