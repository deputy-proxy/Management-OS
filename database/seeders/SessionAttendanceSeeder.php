<?php

namespace Database\Seeders;

use App\Models\SessionAttendance;
use Illuminate\Database\Seeder;

class SessionAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        SessionAttendance::factory()->count(10)->create();
        // </architect:seed>
    }
}