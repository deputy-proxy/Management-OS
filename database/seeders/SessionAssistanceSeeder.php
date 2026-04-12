<?php

namespace Database\Seeders;

use App\Models\SessionAssistance;
use Illuminate\Database\Seeder;

class SessionAssistanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        SessionAssistance::factory()->count(10)->create();
        // </architect:seed>
    }
}