<?php

namespace Database\Seeders;

use App\Models\PotentialPartner;
use Illuminate\Database\Seeder;

class PotentialPartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        PotentialPartner::factory()->count(10)->create();
        // </architect:seed>
    }
}