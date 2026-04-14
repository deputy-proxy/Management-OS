<?php

namespace Database\Seeders;

use App\Models\WorkshopParticipant;
use Illuminate\Database\Seeder;

class WorkshopParticipantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        WorkshopParticipant::factory()->count(10)->create();
        // </architect:seed>
    }
}