<?php

namespace Database\Seeders;

use App\Models\EventParticipant;
use Illuminate\Database\Seeder;

class EventParticipantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EventParticipant::factory()->count(10)->create();
        // </architect:seed>
    }
}