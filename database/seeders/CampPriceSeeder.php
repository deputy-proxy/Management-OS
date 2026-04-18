<?php

namespace Database\Seeders;

use App\Models\CampPrice;
use Illuminate\Database\Seeder;

class CampPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CampPrice::factory()->count(10)->create();
        // </architect:seed>
    }
}