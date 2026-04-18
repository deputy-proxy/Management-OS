<?php

namespace Database\Seeders;

use App\Models\IndividualSession;
use Illuminate\Database\Seeder;

class IndividualSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        IndividualSession::factory()->count(10)->create();
        // </architect:seed>
    }
}