<?php

namespace Database\Seeders;

use App\Models\EmployeePaySlip;
use Illuminate\Database\Seeder;

class EmployeePaySlipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeePaySlip::factory()->count(10)->create();
        // </architect:seed>
    }
}