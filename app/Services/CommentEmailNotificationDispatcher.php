<?php

namespace App\Services;

use App\Jobs\SendCommentCreatedEmail;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CommentEmailNotificationDispatcher
{
    public static function dispatchFor(Model $recipient, Model $commentable, Model $comment, string $title, string $body): void
    {
        if (! $recipient instanceof User || ! app(NotificationPreferenceService::class)->allows($recipient, 'comment.created')) {
            return;
        }

        $payload = [
            'subject' => $title,
            'record_title' => (string) ($commentable->title ?? class_basename($commentable)),
            'comment_body' => $body,
            'url' => url('/admin'),
        ];

        $delivery = NotificationDelivery::query()->firstOrCreate([
            'user_id' => $recipient->getKey(),
            'event_key' => 'comment.created',
            'event_id' => (string) $comment->getKey(),
            'channel' => 'email',
        ], ['status' => 'pending', 'payload' => $payload]);

        if ($delivery->status !== 'sent') {
            $delivery->update(['status' => 'pending', 'payload' => $payload]);
            SendCommentCreatedEmail::dispatch($delivery->getKey())->afterCommit();
        }
    }
}
