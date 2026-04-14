<?php

namespace Database\Seeders;

use App\Models\QuarterlyObjective;
use Illuminate\Database\Seeder;

class QuarterlyObjectiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        QuarterlyObjective::factory()->count(10)->create();
        // </architect:seed>
    }
}