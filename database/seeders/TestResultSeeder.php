<?php

namespace Database\Seeders;

use App\Models\TestResult;
use Illuminate\Database\Seeder;

class TestResultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        TestResult::factory()->count(10)->create();
        // </architect:seed>
    }
}