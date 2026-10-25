<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SyntheticPayrollFixtureTest extends TestCase
{
    public function test_payroll_demo_fixture_is_explicitly_synthetic_and_contains_expected_cases(): void
    {
        $content = file_get_contents(dirname(__DIR__).'/Fixtures/Payroll/synthetic-inputs.json');

        $this->assertIsString($content);

        $fixture = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($fixture['fixture_metadata']['synthetic']);
        $this->assertSame('proposed-example-not-approved', $fixture['fixture_metadata']['schema_status']);
        $this->assertCount(3, $fixture['employees']);
        $this->assertCount(2, $fixture['supplier_invoice_examples']);
        $this->assertCount(3, $fixture['workflow_cases']);
    }

    public function test_unresolved_counterparty_is_not_given_an_automatic_tax_classification(): void
    {
        $content = file_get_contents(dirname(__DIR__).'/Fixtures/Payroll/synthetic-inputs.json');
        $fixture = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        $unresolvedInvoice = $fixture['supplier_invoice_examples'][1];

        $this->assertSame('unresolved_individual_or_pfa', $unresolvedInvoice['counterparty_legal_type']);
        $this->assertSame('unresolved', $unresolvedInvoice['classification_evidence_status']);
        $this->assertNull($unresolvedInvoice['vat_amount']);
        $this->assertNull($unresolvedInvoice['gross_amount']);
    }

    public function test_mid_period_start_and_duplicate_source_are_represented_as_policy_gated_cases(): void
    {
        $content = file_get_contents(dirname(__DIR__).'/Fixtures/Payroll/synthetic-inputs.json');
        $fixture = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        $midPeriodEmployee = $fixture['employees'][2];
        $workflowCaseIds = array_column($fixture['workflow_cases'], 'case_id');

        $this->assertSame('2026-09-16', $midPeriodEmployee['contract']['effective_from']);
        $this->assertStringContainsString('proration must remain undefined', $midPeriodEmployee['expected_behavior']);
        $this->assertContains('CASE-DUPLICATE-SOURCE', $workflowCaseIds);
        $this->assertContains('CASE-CLOSED-PERIOD', $workflowCaseIds);
    }
}
