<?php

namespace Database\Seeders;

use App\Models\EmployeeHoliday;
use Illuminate\Database\Seeder;

class EmployeeHolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeeHoliday::factory()->count(10)->create();
        // </architect:seed>
    }
}