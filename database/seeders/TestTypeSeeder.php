<?php

namespace Database\Seeders;

use App\Models\TestType;
use Illuminate\Database\Seeder;

class TestTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        TestType::factory()->count(10)->create();
        // </architect:seed>
    }
}