<?php

namespace Database\Seeders;

use App\Models\EmployeeEvaluation;
use Illuminate\Database\Seeder;

class EmployeeEvaluationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeeEvaluation::factory()->count(10)->create();
        // </architect:seed>
    }
}