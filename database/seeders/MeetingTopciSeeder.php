<?php

namespace Database\Seeders;

use App\Models\MeetingTopci;
use Illuminate\Database\Seeder;

class MeetingTopciSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        MeetingTopci::factory()->count(10)->create();
        // </architect:seed>
    }
}