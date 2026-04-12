<?php

namespace Database\Seeders;

use App\Models\TestSubmission;
use Illuminate\Database\Seeder;

class TestSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        TestSubmission::factory()->count(10)->create();
        // </architect:seed>
    }
}