<?php

namespace Database\Seeders;

use App\Models\PlacementTest;
use Illuminate\Database\Seeder;

class PlacementTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        PlacementTest::factory()->count(10)->create();
        // </architect:seed>
    }
}