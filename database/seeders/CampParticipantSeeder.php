<?php

namespace Database\Seeders;

use App\Models\CampParticipant;
use Illuminate\Database\Seeder;

class CampParticipantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CampParticipant::factory()->count(10)->create();
        // </architect:seed>
    }
}