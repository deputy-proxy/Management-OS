<?php

namespace Database\Seeders;

use App\Models\EmployeeMedicalLeave;
use Illuminate\Database\Seeder;

class EmployeeMedicalLeaveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeeMedicalLeave::factory()->count(10)->create();
        // </architect:seed>
    }
}