<?php

namespace Database\Seeders;

use App\Models\GroupSession;
use Illuminate\Database\Seeder;

class GroupSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        GroupSession::factory()->count(10)->create();
        // </architect:seed>
    }
}