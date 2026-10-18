<?php

namespace Tests\Feature;

use App\Jobs\SendModelEventEmail;
use App\Mail\ModelEventMail;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_are_opt_in_by_default(): void
    {
        $user = User::factory()->create();
        $this->grantCourseAccess($user);
        $service = app(NotificationPreferenceService::class);

        $this->assertFalse($service->allows($user->fresh(), 'comment.created', 'email'));
        $this->assertFalse($service->allows($user->fresh(), 'comment.created', 'in_app'));
    }

    public function test_catalog_only_contains_models_allowed_by_the_users_current_role(): void
    {
        $user = User::factory()->create();
        $role = HexaRole::query()->create([
            'name' => 'Course viewer',
            'access' => ['course.index'],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $catalog = collect(app(NotificationPreferenceService::class)->catalogFor($user->fresh()));

        $this->assertTrue($catalog->contains('event_key', 'model.course.created'));
        $this->assertTrue($catalog->contains('event_key', 'model.course.updated'));
        $this->assertFalse($catalog->contains('event_key', 'model.customer.created'));
        $this->assertTrue($catalog->contains('event_key', 'comment.created'));
    }

    public function test_saving_preferences_updates_each_channel_and_defaults_missing_values_to_disabled(): void
    {
        $user = User::factory()->create();
        $this->grantCourseAccess($user);
        $service = app(NotificationPreferenceService::class);
        $event = collect($service->catalogFor($user))->firstWhere('event_key', 'comment.created');
        $this->assertNotNull($event);

        $service->savePreferences($user, ['comment.created' => ['email' => true, 'in_app' => false]]);

        $this->assertTrue($service->allows($user, 'comment.created', 'email'));
        $this->assertFalse($service->allows($user->fresh(), 'comment.created', 'in_app'));
    }

    public function test_user_cannot_save_preferences_for_an_event_outside_their_current_role(): void
    {
        $user = User::factory()->create();
        $role = HexaRole::query()->create([
            'name' => 'Course viewer',
            'access' => ['course.index'],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->expectException(ValidationException::class);
        app(NotificationPreferenceService::class)->savePreferences($user->fresh(), [
            'model.customer.created' => ['email' => true, 'in_app' => true],
        ]);
    }

    public function test_role_change_stops_applying_preferences_for_models_no_longer_authorized(): void
    {
        $user = User::factory()->create();
        $role = HexaRole::query()->create([
            'name' => 'Course viewer',
            'access' => ['course.index'],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event_key' => 'model.course.created',
            'channel' => 'email',
            'enabled' => true,
        ]);

        $this->assertTrue(app(NotificationPreferenceService::class)->allows($user->fresh(), 'model.course.created', 'email'));
        $role->update(['access' => ['customer.index']]);

        $this->assertFalse(app(NotificationPreferenceService::class)->allows($user->fresh(), 'model.course.created', 'email'));
        $this->assertFalse(collect(app(NotificationPreferenceService::class)->catalogFor($user->fresh()))->contains(fn (array $event): bool => $event['event_key'] === 'model.course.created'));
    }

    public function test_in_app_delivery_is_opt_in_and_deduplicated(): void
    {
        $user = User::factory()->create();
        $this->grantCourseAccess($user);
        $service = app(NotificationPreferenceService::class);
        $payload = ['subject' => 'Course created', 'message' => 'A course was created.'];

        $service->deliverInApp($user, 'comment.created', 'comment-1', $payload);
        $this->assertDatabaseMissing('notification_deliveries', ['user_id' => $user->id, 'event_key' => 'comment.created', 'channel' => 'in_app']);

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event_key' => 'comment.created',
            'channel' => 'in_app',
            'enabled' => true,
        ]);
        $service->deliverInApp($user, 'comment.created', 'comment-1', $payload);
        $service->deliverInApp($user, 'comment.created', 'comment-1', $payload);

        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'event_key' => 'comment.created', 'event_id' => 'comment-1', 'channel' => 'in_app', 'status' => 'sent']);

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event_key' => 'model.course.created',
            'channel' => 'in_app',
            'enabled' => true,
        ]);
        $service->deliverInApp($user, 'model.course.created', 'course-1-created', $payload);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'event_key' => 'model.course.created', 'event_id' => 'course-1-created', 'channel' => 'in_app', 'status' => 'sent']);
    }

    public function test_model_event_preference_cannot_be_used_for_a_different_model(): void
    {
        $user = User::factory()->create();
        $this->grantCourseAccess($user);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event_key' => 'model.course.created',
            'channel' => 'email',
            'enabled' => true,
        ]);

        $customer = Customer::factory()->create();

        $this->assertFalse(app(NotificationPreferenceService::class)->allows($user->fresh(), 'model.course.created', 'email', $customer));
    }

    public function test_model_event_email_delivery_respects_preferences_and_is_idempotent(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->grantCourseAccess($user);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event_key' => 'model.course.created',
            'channel' => 'email',
            'enabled' => true,
        ]);
        $delivery = NotificationDelivery::query()->create([
            'user_id' => $user->id,
            'event_key' => 'model.course.created',
            'event_id' => 'course-1-created',
            'channel' => 'email',
            'status' => 'pending',
            'payload' => ['subject' => 'Course created', 'record_title' => 'Course A', 'message' => 'Course A was created.', 'url' => 'https://example.test/admin'],
        ]);

        (new SendModelEventEmail($delivery->id))->handle(app(NotificationPreferenceService::class));
        (new SendModelEventEmail($delivery->id))->handle(app(NotificationPreferenceService::class));

        Mail::assertSent(ModelEventMail::class, 1);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent']);
    }

    public function test_model_event_email_is_skipped_when_preference_is_disabled(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->grantCourseAccess($user);
        $delivery = NotificationDelivery::query()->create([
            'user_id' => $user->id,
            'event_key' => 'model.course.created',
            'event_id' => 'course-2-created',
            'channel' => 'email',
            'status' => 'pending',
            'payload' => ['subject' => 'Course created', 'record_title' => 'Course B', 'message' => 'Course B was created.', 'url' => 'https://example.test/admin'],
        ]);

        (new SendModelEventEmail($delivery->id))->handle(app(NotificationPreferenceService::class));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'skipped']);
    }

    public function test_only_supported_channels_can_be_enabled(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        app(NotificationPreferenceService::class)->savePreferences($user, [
            'comment.created' => ['sms' => true],
        ]);
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
