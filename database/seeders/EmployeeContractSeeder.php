<?php

namespace Database\Seeders;

use App\Models\EmployeeContract;
use Illuminate\Database\Seeder;

class EmployeeContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EmployeeContract::factory()->count(10)->create();
        // </architect:seed>
    }
}