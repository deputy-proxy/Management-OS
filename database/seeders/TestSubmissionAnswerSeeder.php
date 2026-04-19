<?php

namespace Database\Seeders;

use App\Models\TestSubmissionAnswer;
use Illuminate\Database\Seeder;

class TestSubmissionAnswerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        TestSubmissionAnswer::factory()->count(10)->create();
        // </architect:seed>
    }
}