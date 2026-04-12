<?php

namespace Database\Seeders;

use App\Models\CompetitorOffer;
use Illuminate\Database\Seeder;

class CompetitorOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CompetitorOffer::factory()->count(10)->create();
        // </architect:seed>
    }
}