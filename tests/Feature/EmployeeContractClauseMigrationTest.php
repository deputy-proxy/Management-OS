<?php

namespace Tests\Feature;

use App\Models\EmployeeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeContractClauseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_equipment_reimbursement_clause_type_is_normalized(): void
    {
        $contract = EmployeeContract::factory()->create([
            'clauses' => [[
                'type' => 'UseOwnEquipmentReimbursment',
                'data' => ['amount' => 125.50],
            ]],
        ]);

        $migration = require base_path('database/migrations/2026_10_18_000005_normalize_equipment_reimbursement_clause_key.php');
        $migration->up();

        $contract->refresh();
        $this->assertSame('use_own_equipment_reimbursement', $contract->clauses[0]['type']);
        $this->assertSame(125.5, $contract->equipmentReimbursementAmount());
    }
}
