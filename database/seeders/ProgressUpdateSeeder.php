<?php

namespace Database\Seeders;

use App\Models\ProgressUpdate;
use Illuminate\Database\Seeder;

class ProgressUpdateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        ProgressUpdate::factory()->count(10)->create();
        // </architect:seed>
    }
}