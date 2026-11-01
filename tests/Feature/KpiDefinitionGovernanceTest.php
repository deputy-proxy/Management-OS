<?php

namespace Tests\Feature;

use App\Models\KpiDefinition;
use App\Models\KpiDefinitionAudit;
use App\Models\User;
use App\Services\KpiDefinitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KpiDefinitionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_queries_require_view_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.index', fn (): bool => false);

        try {
            app(KpiDefinitionService::class)->queryForCurrentUser();
            $this->fail('Expected view permission to be enforced by the service.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        Gate::define('kpi_definition.index', fn (): bool => true);
        $this->assertSame(0, app(KpiDefinitionService::class)->queryForCurrentUser()->count());
    }

    public function test_service_create_and_update_require_their_own_permissions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.create', fn (): bool => false);
        Gate::define('kpi_definition.update', fn (): bool => false);

        try {
            app(KpiDefinitionService::class)->createDraft([
                'key' => 'customer_record_count',
                'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
            ]);
            $this->fail('Expected create permission to be enforced by the service.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('kpi_definitions', 0);
        }

        Gate::define('kpi_definition.create', fn (): bool => true);
        $definition = app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
            'change_summary' => 'Initial approved catalogue configuration draft.',
        ]);

        try {
            app(KpiDefinitionService::class)->updateDraft($definition, ['target_value' => 25, 'change_summary' => 'Set target.']);
            $this->fail('Expected update permission to be enforced by the service.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('kpi_definitions', ['id' => $definition->id, 'target_value' => null]);
        }
    }

    public function test_service_records_actor_summary_and_version_for_every_lifecycle_change(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.update', fn (): bool => true);
        Gate::define('kpi_definition.approve', fn (): bool => true);

        $effectiveFrom = Carbon::tomorrow('Europe/Bucharest')->toDateString();
        $service = app(KpiDefinitionService::class);
        $definition = $service->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => $effectiveFrom,
            'change_summary' => 'Create versioned customer count definition.',
        ]);
        $service->updateDraft($definition, [
            'target_value' => 25,
            'change_summary' => 'Set the approved planning target to 25.',
        ]);
        $service->activate($definition->fresh());

        $audits = KpiDefinitionAudit::query()->where('kpi_definition_id', $definition->id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'activated'], $audits->pluck('event')->all());
        $this->assertSame([$user->id, $user->id, $user->id], $audits->pluck('actor_id')->all());
        $this->assertSame([1, 1, 1], $audits->pluck('effective_version')->all());
        $this->assertSame('Set the approved planning target to 25.', $audits[1]->change_summary);
        $this->assertSame('25.000000', (string) $audits[1]->changes['target_value']['after']);
        $this->assertNotNull($audits[2]->created_at);
    }

    public function test_service_denies_activation_and_retirement_without_approval_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.approve', fn (): bool => false);
        $definition = app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
        ]);

        try {
            app(KpiDefinitionService::class)->activate($definition);
            $this->fail('Expected activation permission to be enforced.');
        } catch (AuthorizationException) {
            $this->assertSame(KpiDefinition::STATUS_DRAFT, $definition->fresh()->status);
        }

        Gate::define('kpi_definition.approve', fn (): bool => true);
        app(KpiDefinitionService::class)->activate($definition->fresh());
        Gate::define('kpi_definition.approve', fn (): bool => false);
        try {
            app(KpiDefinitionService::class)->retire($definition->fresh());
            $this->fail('Expected retirement permission to be enforced.');
        } catch (AuthorizationException) {
            $this->assertSame(KpiDefinition::STATUS_ACTIVE, $definition->fresh()->status);
        }
    }

    public function test_audit_records_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.create', fn (): bool => true);
        $definition = app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
        ]);
        $audit = KpiDefinitionAudit::query()->where('kpi_definition_id', $definition->id)->firstOrFail();

        try {
            $audit->update(['change_summary' => 'Tampered history.']);
            $this->fail('Expected audit updates to be rejected.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('kpi_definition_audits', ['id' => $audit->id, 'change_summary' => 'Created KPI definition draft.']);
        }

        $this->expectException(\LogicException::class);
        $audit->delete();
    }

    public function test_service_rejects_attempts_to_change_non_draft_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.update', fn (): bool => true);
        $definition = app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        app(KpiDefinitionService::class)->updateDraft($definition, [
            'status' => KpiDefinition::STATUS_ACTIVE,
            'change_summary' => 'Attempt to bypass activation workflow.',
        ]);
    }
}
