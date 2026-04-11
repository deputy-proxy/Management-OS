<?php

namespace Database\Seeders;

use App\Models\EmployeeTimesheet;
use Illuminate\Database\Seeder;

class EmployeeTimesheetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeeTimesheet::factory()->count(10)->create();
        // </architect:seed>
    }
}