<?php

namespace Database\Seeders;

use App\Models\TestQuestion;
use Illuminate\Database\Seeder;

class TestQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        TestQuestion::factory()->count(10)->create();
        // </architect:seed>
    }
}