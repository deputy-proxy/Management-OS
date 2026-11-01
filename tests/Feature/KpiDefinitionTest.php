<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\KpiDefinition;
use App\Models\User;
use App\Services\KpiAggregationService;
use App\Services\KpiDefinitionConfiguration;
use App\Services\KpiDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class KpiDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_copy_semantics_from_the_allow_list_and_increment_versions(): void
    {
        $first = KpiDefinition::query()->create(['key' => 'customer_record_count', 'effective_from' => '2026-10-27']);
        $second = KpiDefinition::query()->create(['key' => 'customer_record_count', 'effective_from' => '2026-11-01']);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('customer_record_count', $first->calculation_type);
        $this->assertSame('Customer records', $first->label);
        $this->assertSame(KpiDefinition::STATUS_DRAFT, $first->status);
    }

    public function test_unregistered_kpi_keys_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        KpiDefinition::query()->create(['key' => 'arbitrary_php_formula', 'effective_from' => '2026-10-27']);
    }

    public function test_activation_versions_definitions_and_keeps_prior_effective_dates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.approve', fn (): bool => true);

        $firstDate = Carbon::tomorrow('Europe/Bucharest')->toDateString();
        $secondDate = Carbon::tomorrow('Europe/Bucharest')->addDays(5)->toDateString();

        $first = KpiDefinition::query()->create(['key' => 'customer_record_count', 'effective_from' => $firstDate]);
        app(KpiDefinitionService::class)->activate($first);
        $second = KpiDefinition::query()->create(['key' => 'customer_record_count', 'effective_from' => $secondDate]);
        app(KpiDefinitionService::class)->activate($second);

        $this->assertSame(KpiDefinition::STATUS_ACTIVE, $first->fresh()->status);
        $this->assertSame(Carbon::parse($secondDate)->subDay()->toDateString(), $first->fresh()->effective_until->toDateString());
        $this->assertSame(KpiDefinition::STATUS_ACTIVE, $second->fresh()->status);
        $this->assertSame($user->id, $second->fresh()->approved_by);
        $this->assertNotNull($second->fresh()->approved_at);
    }

    public function test_activation_rejects_a_retroactive_effective_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        $definition = KpiDefinition::query()->create(['key' => 'customer_record_count', 'effective_from' => '2020-01-01']);

        try {
            app(KpiDefinitionService::class)->activate($definition);
            $this->fail('Expected retroactive KPI activation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }
    }

    public function test_active_definition_semantics_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        $definition = KpiDefinition::query()->create([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
        ]);
        app(KpiDefinitionService::class)->activate($definition);

        $this->expectException(LogicException::class);
        $definition->update(['label' => 'Changed meaning']);
    }

    public function test_threshold_order_is_validated_for_direction(): void
    {
        $this->expectException(ValidationException::class);
        KpiDefinition::query()->create([
            'key' => 'overdue_task_count',
            'effective_from' => '2026-10-27',
            'threshold_direction' => 'higher_is_worse',
            'warning_threshold' => 10,
            'critical_threshold' => 5,
        ]);
    }

    public function test_aggregation_is_permission_gated_and_returns_a_typed_result(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        Gate::define('customer.index', fn (): bool => true);
        $definition = KpiDefinition::query()->create([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::now()->toDateString(),
            'target_value' => 1,
            'threshold_direction' => 'higher_is_worse',
            'warning_threshold' => 1,
            'critical_threshold' => 2,
        ]);
        app(KpiDefinitionService::class)->activate($definition);
        Customer::factory()->create();

        $result = app(KpiAggregationService::class)->aggregate($definition->fresh(), ['customer.index' => true]);
        $this->assertSame('customer_record_count', $result['key']);
        $this->assertSame(1, $result['value']);
        $this->assertSame('warning', $result['threshold_status']);
        $this->assertSame('available', $result['availability']);

        Gate::define('customer.index', fn (): bool => false);
        $restricted = app(KpiAggregationService::class)->aggregate($definition->fresh(), ['customer.index' => false]);
        $this->assertNull($restricted['value']);
        $this->assertSame('unavailable_permission_denied', $restricted['availability']);
    }

    public function test_configuration_rejects_unregistered_fields_operators_and_unbounded_filters(): void
    {
        $configuration = app(KpiDefinitionConfiguration::class);

        foreach ([
            fn () => $configuration->validate('unconverted_lead_count', [['field' => 'email', 'operator' => 'eq', 'value' => 'a@example.test']]),
            fn () => $configuration->validate('unconverted_lead_count', [['field' => 'status', 'operator' => 'raw', 'value' => 'new']]),
            fn () => $configuration->validate('unconverted_lead_count', array_fill(0, 6, ['field' => 'status', 'operator' => 'eq', 'value' => 'new'])),
            fn () => $configuration->validate('unconverted_lead_count', [], [], 'sum'),
            fn () => $configuration->validate('unconverted_lead_count', [], [], 'count', ['kind' => 'range', 'granularity' => 'day', 'historical' => true]),
        ] as $invalidConfiguration) {
            try {
                $invalidConfiguration();
                $this->fail('Expected unsupported KPI configuration to be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_synthetic_preview_applies_validated_filters_and_dimensions(): void
    {
        $preview = app(KpiDefinitionConfiguration::class)->preview('unconverted_lead_count', [
            'filters' => [['field' => 'status', 'operator' => 'neq', 'value' => 'lost']],
            'dimensions' => ['source_category'],
        ], [
            ['status' => 'new', 'source_category' => 'organic_search'],
            ['status' => 'lost', 'source_category' => 'referral'],
            ['status' => 'qualified', 'source_category' => 'organic_search'],
        ]);

        $this->assertTrue($preview['synthetic']);
        $this->assertSame(3, $preview['sample_size']);
        $this->assertSame(2, $preview['value']);
        $this->assertSame(['source_category=organic_search' => 2], $preview['breakdown']);
    }

    public function test_registered_lead_filters_and_dimensions_are_applied_to_live_aggregation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        Gate::define('customer_lead.index', fn (): bool => true);

        CustomerLead::factory()->create(['status' => 'new', 'source_category' => 'organic_search']);
        CustomerLead::factory()->create(['status' => 'lost', 'source_category' => 'referral']);

        $definition = KpiDefinition::query()->create([
            'key' => 'unconverted_lead_count',
            'effective_from' => Carbon::now('Europe/Bucharest')->toDateString(),
            'filters' => [['field' => 'status', 'operator' => 'eq', 'value' => 'new']],
            'dimensions' => ['source_category'],
        ]);
        app(KpiDefinitionService::class)->activate($definition);

        $result = app(KpiAggregationService::class)->aggregate($definition->fresh(), ['customer_lead.index' => true]);

        $this->assertSame(1, $result['value']);
        $this->assertSame(['source_category=organic_search' => 1], $result['dimension_breakdown']);
    }

    public function test_synthetic_preview_rejects_more_than_one_hundred_rows(): void
    {
        $this->expectException(ValidationException::class);
        app(KpiDefinitionConfiguration::class)->preview('unconverted_lead_count', [], array_fill(0, 101, []));
    }
}
