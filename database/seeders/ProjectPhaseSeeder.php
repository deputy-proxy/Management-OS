<?php

namespace Database\Seeders;

use App\Models\ProjectPhase;
use Illuminate\Database\Seeder;

class ProjectPhaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        ProjectPhase::factory()->count(10)->create();
        // </architect:seed>
    }
}