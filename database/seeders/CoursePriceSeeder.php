<?php

namespace Database\Seeders;

use App\Models\CoursePrice;
use Illuminate\Database\Seeder;

class CoursePriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CoursePrice::factory()->count(10)->create();
        // </architect:seed>
    }
}