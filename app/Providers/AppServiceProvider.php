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
use App\Models\User;

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
        ]);

Event::listen(CommentCreated::class, function (CommentCreated $event) {
            $commentable = $event->commentable;
            $comment = $event->comment;

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

            $title = 'New comment on '.class_basename($commentable).': '.$event->commentable->title; ;
            $body = $comment->body;

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
