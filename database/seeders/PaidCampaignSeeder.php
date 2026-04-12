<?php

namespace Database\Seeders;

use App\Models\PaidCampaign;
use Illuminate\Database\Seeder;

class PaidCampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        PaidCampaign::factory()->count(10)->create();
        // </architect:seed>
    }
}