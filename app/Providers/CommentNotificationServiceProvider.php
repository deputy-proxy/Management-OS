<?php

namespace App\Providers;

// Ensure this matches your package
use App\Models\User;
use App\Services\CommentEmailNotificationDispatcher;
use App\Services\NotificationPreferenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Relaticle\Comments\Events\CommentCreated;
use Relaticle\Comments\Models\Subscription;

class CommentNotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CommentCreated::class, function (CommentCreated $event) {
            $commentable = $event->commentable;
            $comment = $event->comment;
            $title = 'New comment on '.class_basename($commentable).': '.($commentable->title ?? class_basename($commentable));
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
                ->filter(fn ($recipient) => ! ($recipient instanceof Model
                    && method_exists($recipient, 'is')
                    && $comment->commenter instanceof Model
                    && $recipient->is($comment->commenter)))
                ->values();

            foreach ($recipients as $recipient) {
                if ($recipient instanceof User) {
                    app(NotificationPreferenceService::class)->deliverInApp(
                        $recipient,
                        'comment.created',
                        (string) $comment->getKey(),
                        ['subject' => $title, 'message' => (string) $body],
                        $commentable
                    );
                }

                CommentEmailNotificationDispatcher::dispatchFor(
                    $recipient,
                    $commentable,
                    $comment,
                    $title,
                    (string) $body
                );
            }
        });
    }
}
