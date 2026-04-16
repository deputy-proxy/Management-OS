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
            'course' => \App\Models\Course::class,
            'user' => \App\Models\User::class,
            'article' => \App\Models\Article::class,
            'job_description' => \App\Models\JobDescription::class,
            'employee_evaluation_form' => \App\Models\EmployeeEvaluationForm::class,
            'competition' => \App\Models\Competition::class,
            'competition_entry' => \App\Models\CompetitionEntry::class,
            'content_template' => \App\Models\ContentTemplate::class,
            'customer' => \App\Models\Customer::class,
            'competitor' => \App\Models\Competitor::class,
            'employee' => \App\Models\Employee::class,
            'employee_contract' => \App\Models\EmployeeContract::class,
            'event' => \App\Models\Event::class,
            'event_participant' => \App\Models\EventParticipant::class,
            'group' => \App\Models\Group::class,
            'group_session' => \App\Models\GroupSession::class,
            'project' => \App\Models\Project::class,
            'newsletter_subscriber' => \App\Models\NewsletterSubscriber::class,
            'product_review' => \App\Models\ProductReview::class,
            'quarterly_objective' => \App\Models\QuarterlyObjective::class,
            'vendor_category' => \App\Models\VendorCategory::class,
            'vendor_contract' => \App\Models\VendorContract::class,
            'vendor' => \App\Models\Vendor::class,
            'workshop_participant' => \App\Models\WorkshopParticipant::class,
            'workshop' => \App\Models\Workshop::class,
            'employee_evaluation" => \App\Models\EmployeeEvaluation::clas,
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
