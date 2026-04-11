<?php

namespace Database\Seeders;

use App\Models\JobDescription;
use Illuminate\Database\Seeder;

class JobDescriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        JobDescription::factory()->count(10)->create();
        // </architect:seed>
    }
}