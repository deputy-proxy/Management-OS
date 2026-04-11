<?php

namespace Database\Seeders;

use App\Models\EmployeeMedicalLeafe;
use Illuminate\Database\Seeder;

class EmployeeMedicalLeafeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeeMedicalLeafe::factory()->count(10)->create();
        // </architect:seed>
    }
}