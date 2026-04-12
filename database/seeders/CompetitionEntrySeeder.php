<?php

namespace Database\Seeders;

use App\Models\CompetitionEntry;
use Illuminate\Database\Seeder;

class CompetitionEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CompetitionEntry::factory()->count(10)->create();
        // </architect:seed>
    }
}