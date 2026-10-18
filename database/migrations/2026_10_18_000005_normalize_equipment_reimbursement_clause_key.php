<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_contracts', 'clauses')) {
            return;
        }

        $legacyKeys = ['UseOwnEquipmentReimbursment', 'UseOwnEquipmentReimbursement', 'use_own_equipment_reimbursment'];
        foreach (DB::table('employee_contracts')->select('id', 'clauses')->get() as $contract) {
            $clauses = is_array($contract->clauses) ? $contract->clauses : json_decode((string) $contract->clauses, true);
            if (! is_array($clauses)) {
                continue;
            }

            $changed = false;
            foreach ($clauses as &$clause) {
                if (! is_array($clause) || ! in_array($clause['type'] ?? null, $legacyKeys, true)) {
                    continue;
                }

                $clause['type'] = 'use_own_equipment_reimbursement';
                $changed = true;
            }
            unset($clause);

            if ($changed) {
                DB::table('employee_contracts')->where('id', $contract->id)->update(['clauses' => json_encode($clauses, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        // Keep the canonical spelling; the application still reads legacy keys for compatibility.
    }
};
