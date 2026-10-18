<?php

namespace Tests\Feature;

use App\Jobs\SendCommentCreatedEmail;
use App\Mail\CommentCreatedMail;
use App\Models\Course;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommentEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_disabled_without_an_explicit_preference(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(app(NotificationPreferenceService::class)->allows($user, 'comment.created'));
    }

    public function test_enabled_preference_sends_email_and_marks_delivery_sent(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grantCourseAccess($user);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event_key' => 'comment.created',
            'channel' => 'email',
            'enabled' => true,
        ]);
        $delivery = NotificationDelivery::query()->create([
            'user_id' => $user->id,
            'event_key' => 'comment.created',
            'event_id' => 'comment-123',
            'channel' => 'email',
            'status' => 'pending',
            'payload' => ['subject' => 'New comment', 'record_title' => 'Record A', 'comment_body' => 'Hello', 'url' => 'https://example.test/admin', 'model_class' => Course::class, 'model_id' => (string) $course->id],
        ]);

        (new SendCommentCreatedEmail($delivery->id))->handle(app(NotificationPreferenceService::class));

        Mail::assertSent(CommentCreatedMail::class);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent']);
    }

    public function test_disabled_preference_skips_queued_delivery(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grantCourseAccess($user);
        $delivery = NotificationDelivery::query()->create([
            'user_id' => $user->id,
            'event_key' => 'comment.created',
            'event_id' => 'comment-124',
            'channel' => 'email',
            'status' => 'pending',
            'payload' => ['subject' => 'New comment', 'record_title' => 'Record A', 'comment_body' => 'Hello', 'url' => 'https://example.test/admin', 'model_class' => Course::class, 'model_id' => (string) $course->id],
        ]);

        (new SendCommentCreatedEmail($delivery->id))->handle(app(NotificationPreferenceService::class));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'skipped']);
    }

    public function test_sent_delivery_is_not_sent_twice(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event_key' => 'comment.created',
            'channel' => 'email',
            'enabled' => true,
        ]);
        $delivery = NotificationDelivery::query()->create([
            'user_id' => $user->id,
            'event_key' => 'comment.created',
            'event_id' => 'comment-125',
            'channel' => 'email',
            'status' => 'sent',
            'sent_at' => now(),
            'payload' => ['subject' => 'New comment', 'record_title' => 'Record A', 'comment_body' => 'Hello', 'url' => 'https://example.test/admin'],
        ]);

        (new SendCommentCreatedEmail($delivery->id))->handle(app(NotificationPreferenceService::class));

        Mail::assertNothingSent();
    }

    private function grantCourseAccess(User $user): void
    {
        $role = HexaRole::query()->create([
            'name' => 'Course viewer '.$user->id,
            'access' => ['course.index'],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());
    }
}
