<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExecutiveKpiCatalogueTest extends TestCase
{
    public function test_catalogue_v1_contains_only_well_formed_allow_listed_definitions(): void
    {
        $catalogue = config('kpi_catalogue');
        $this->assertSame('v1', $catalogue['version']);
        $this->assertSame('Europe/Bucharest', $catalogue['timezone']);
        $this->assertNotEmpty($catalogue['definitions']);

        $allowedCalculationTypes = [
            'customer_record_count',
            'unconverted_lead_count',
            'unknown_lead_source_count',
            'open_task_count',
            'overdue_task_count',
            'competitor_offer_snapshot_count',
        ];
        foreach ($catalogue['definitions'] as $key => $definition) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]+$/', $key);
            $this->assertContains($definition['calculation_type'], $allowedCalculationTypes);
            $this->assertNotSame('', trim($definition['label']));
            $this->assertNotSame('', trim($definition['source']));
            $this->assertNotSame('', trim($definition['permission']));
            $this->assertNotSame('', trim($definition['missing_data']));
            $this->assertNotSame('', trim($definition['description']));
            $this->assertContains($definition['unit'], ['records', 'snapshots']);
        }
    }

    public function test_catalogue_does_not_define_unapproved_financial_or_lifetime_metrics(): void
    {
        $keys = array_keys(config('kpi_catalogue.definitions'));
        $this->assertNotContains('cac', $keys);
        $this->assertNotContains('customer_lifetime_value', $keys);
        $this->assertNotContains('revenue', $keys);
        $this->assertNotContains('profit_margin', $keys);
    }
}
