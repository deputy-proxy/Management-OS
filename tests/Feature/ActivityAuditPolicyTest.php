<?php

namespace Tests\Feature;

use AlizHarb\ActivityLog\Policies\ActivityPolicy;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityAuditPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_activity_logs_only_the_approved_business_field_and_never_personal_identifiers(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Private Customer Name',
            'customer_type' => 'individual',
            'cnp_cui' => 'SENSITIVE-CNP-CUI',
            'email' => 'private-customer@example.test',
            'address' => 'Sensitive street address',
        ]);

        Activity::query()->delete();

        $customer->update([
            'name' => 'Changed Private Name',
            'customer_type' => 'company',
            'cnp_cui' => 'CHANGED-SENSITIVE-ID',
            'email' => 'changed-private@example.test',
            'address' => 'Changed private address',
        ]);

        $activity = Activity::query()->where('subject_type', $customer->getMorphClass())
            ->where('subject_id', $customer->getKey())
            ->latest('id')
            ->firstOrFail();

        $serializedChanges = json_encode([
            'properties' => $activity->properties,
            'attribute_changes' => $activity->getAttribute('attribute_changes'),
        ], JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('company', $serializedChanges);
        $this->assertStringNotContainsString('SENSITIVE-CNP-CUI', $serializedChanges);
        $this->assertStringNotContainsString('CHANGED-SENSITIVE-ID', $serializedChanges);
        $this->assertStringNotContainsString('private-customer@example.test', $serializedChanges);
        $this->assertStringNotContainsString('changed-private@example.test', $serializedChanges);
        $this->assertStringNotContainsString('Sensitive street address', $serializedChanges);
        $this->assertStringNotContainsString('Changed Private Name', $serializedChanges);
    }

    public function test_password_changes_do_not_create_an_attribute_value_audit_entry(): void
    {
        $user = User::factory()->create();
        Activity::query()->delete();

        $user->update(['password' => 'replacement-password-that-must-not-be-logged']);

        $activity = Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->getKey())
            ->latest('id')
            ->firstOrFail();

        $serialized = json_encode([
            'properties' => $activity->properties,
            'attribute_changes' => $activity->getAttribute('attribute_changes'),
        ], JSON_THROW_ON_ERROR);

        $this->assertSame('updated', $activity->event);
        $this->assertStringNotContainsString('replacement-password-that-must-not-be-logged', $serialized);
        $this->assertStringNotContainsString('password', $serialized);
    }

    public function test_activity_log_viewing_requires_the_explicit_permission_and_mutation_is_denied(): void
    {
        config([
            'filament-activity-log.permissions.enabled' => true,
            'filament-activity-log.permissions.custom_authorization' => null,
            'filament-activity-log.permissions.view_any' => 'activity-log.index',
            'filament-activity-log.permissions.view' => 'activity-log.index',
            'filament-activity-log.permissions.update' => 'activity-log.manage',
            'filament-activity-log.permissions.delete' => 'activity-log.manage',
        ]);

        Gate::define('activity-log.index', fn (User $user): bool => $user->roles()->get()->contains(
            fn ($role): bool => in_array('activity-log.index', collect($role->access ?? [])->flatten()->all(), true),
        ));
        Gate::define('activity-log.manage', fn (): bool => false);

        $policy = app(ActivityPolicy::class);
        $unprivileged = User::factory()->create();
        $this->assertFalse($policy->viewAny($unprivileged));

        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Audit reader '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Audit' => ['activity-log.index']],
            'guard' => 'web',
        ]);
        $reader = User::factory()->create();
        $reader->roles()->attach($role->getKey());

        $this->assertTrue($policy->viewAny($reader));
        $this->assertTrue($policy->view($reader, new Activity));
        $this->assertFalse($policy->update($reader, new Activity));
        $this->assertFalse($policy->delete($reader, new Activity));
    }

    public function test_activity_log_ui_disables_destructive_actions_and_uses_a_one_year_configured_retention_window(): void
    {
        $this->assertSame(365, config('activitylog.clean_after_days'));
        $this->assertTrue(config('filament-activity-log.permissions.enabled'));
        $this->assertNull(config('filament-activity-log.permissions.custom_authorization'));
        $this->assertSame('activity-log.index', config('filament-activity-log.permissions.view_any'));
        $this->assertFalse(config('filament-activity-log.table.actions.revert'));
        $this->assertFalse(config('filament-activity-log.table.actions.restore'));
        $this->assertFalse(config('filament-activity-log.table.actions.delete'));
        $this->assertFalse(config('filament-activity-log.table.actions.prune'));
        $this->assertFalse(config('filament-activity-log.table.bulk_actions.delete'));
        foreach (['password', 'remember_token', 'email', 'cnp_cui', 'date_of_birth', 'answer', 'correct_answer', 'document', 'content'] as $sensitiveAttribute) {
            $this->assertContains($sensitiveAttribute, config('activitylog.default_except_attributes'));
        }
    }

    public function test_every_activity_model_uses_an_explicit_log_only_policy(): void
    {
        foreach (glob(app_path('Models/*.php')) as $path) {
            $source = file_get_contents($path);
            if (! str_contains($source, 'use LogsActivity;')) {
                continue;
            }

            $this->assertStringNotContainsString('logAll()', $source, basename($path).' must not use logAll().');
            $this->assertStringContainsString('function getActivitylogOptions()', $source, basename($path).' must declare its policy.');
            $this->assertStringContainsString('logOnly(', $source, basename($path).' must use an explicit allowlist.');
        }
    }
}
