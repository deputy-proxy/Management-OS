<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class WorkerArrangementInventoryFixtureTest extends TestCase
{
    public function test_worker_arrangement_fixture_is_synthetic_and_not_owner_approved(): void
    {
        $content = file_get_contents(dirname(__DIR__).'/Fixtures/Payroll/worker-arrangements-synthetic.json');

        $this->assertIsString($content);

        $fixture = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($fixture['fixture_metadata']['synthetic']);
        $this->assertSame('example-only-not-owner-reviewed', $fixture['fixture_metadata']['review_status']);
        $this->assertCount(4, $fixture['arrangements']);
    }

    public function test_unresolved_individual_and_other_arrangements_are_not_routed_to_payroll(): void
    {
        $fixture = json_decode(
            file_get_contents(dirname(__DIR__).'/Fixtures/Payroll/worker-arrangements-synthetic.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach (array_slice($fixture['arrangements'], 2) as $arrangement) {
            $this->assertSame('unresolved', $arrangement['classification_status']);
            $this->assertSame('blocked_pending_review', $arrangement['payroll_workflow']);
            $this->assertFalse($arrangement['contractor_invoice_workflow_allowed']);
            $this->assertNotEmpty($arrangement['evidence_required']);
        }
    }

    public function test_employee_and_supplier_examples_remain_distinct_workflows(): void
    {
        $fixture = json_decode(
            file_get_contents(dirname(__DIR__).'/Fixtures/Payroll/worker-arrangements-synthetic.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $employee = $fixture['arrangements'][0];
        $supplier = $fixture['arrangements'][1];

        $this->assertSame('existing_employee_payslip_workflow', $employee['payroll_workflow']);
        $this->assertFalse($employee['contractor_invoice_workflow_allowed']);
        $this->assertSame('not_applicable', $supplier['payroll_workflow']);
        $this->assertTrue($supplier['contractor_invoice_workflow_allowed']);
    }
}
