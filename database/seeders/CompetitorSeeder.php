<?php

namespace Database\Seeders;

use App\Models\Competitor;
use Illuminate\Database\Seeder;

class CompetitorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        Competitor::factory()->count(10)->create();
        // </architect:seed>
    }
}