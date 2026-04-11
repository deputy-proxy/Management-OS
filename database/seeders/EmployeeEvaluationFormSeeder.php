<?php

namespace Database\Seeders;

use App\Models\EmployeeEvaluationForm;
use Illuminate\Database\Seeder;

class EmployeeEvaluationFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeeEvaluationForm::factory()->count(10)->create();
        // </architect:seed>
    }
}