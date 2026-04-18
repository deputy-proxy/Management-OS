<?php

namespace Database\Seeders;

use App\Models\MeetingTopic;
use Illuminate\Database\Seeder;

class MeetingTopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        MeetingTopic::factory()->count(10)->create();
        // </architect:seed>
    }
}