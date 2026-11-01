<?php

namespace Tests\Feature;

use App\Services\KpiAggregationService;
use App\Services\KpiCatalogueRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class KpiCatalogueRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_stable_keys_are_rejected(): void
    {
        $catalogue = config('kpi_catalogue');
        $catalogue['definitions'][] = $catalogue['definitions'][0];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate KPI catalogue key [customer_record_count].');

        (new KpiCatalogueRegistry($catalogue))->all();
    }

    public function test_unsupported_dimensions_and_period_contracts_are_rejected(): void
    {
        $catalogue = config('kpi_catalogue');
        $catalogue['definitions'][0]['dimensions'] = ['region'];

        try {
            (new KpiCatalogueRegistry($catalogue))->all();
            $this->fail('An unregistered dimension must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('unsupported dimension', $exception->getMessage());
        }

        $catalogue = config('kpi_catalogue');
        $catalogue['definitions'][0]['supported_period']['kind'] = 'date_range';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supported daily as-of period contract');
        (new KpiCatalogueRegistry($catalogue))->all();
    }

    public function test_missing_source_or_calculation_contract_is_reported_as_unavailable(): void
    {
        $catalogue = config('kpi_catalogue');
        $catalogue['definitions'][0]['source_service'] = 'App\\Models\\MissingKpiSource';
        $registry = new KpiCatalogueRegistry($catalogue);
        app()->instance(KpiCatalogueRegistry::class, $registry);
        $this->assertArrayNotHasKey('customer_record_count', $registry->options());

        $results = app(KpiAggregationService::class)->current(
            ['customer.index' => true],
            CarbonImmutable::parse('2026-10-27 12:00:00', 'Europe/Bucharest'),
        );
        $customer = collect($results['results'])->firstWhere('key', 'customer_record_count');

        $this->assertNull($customer['value']);
        $this->assertSame('unavailable_missing_source', $customer['availability']);
        $this->assertNotNull($customer['unavailable_reason']);

        $catalogue = config('kpi_catalogue');
        $catalogue['definitions'][0]['calculation_type'] = 'unregistered_calculation';
        app()->instance(KpiCatalogueRegistry::class, new KpiCatalogueRegistry($catalogue));

        $results = app(KpiAggregationService::class)->current(
            ['customer.index' => true],
            CarbonImmutable::parse('2026-10-27 12:00:00', 'Europe/Bucharest'),
        );
        $customer = collect($results['results'])->firstWhere('key', 'customer_record_count');

        $this->assertNull($customer['value']);
        $this->assertSame('unavailable_missing_calculation', $customer['availability']);
    }
}
