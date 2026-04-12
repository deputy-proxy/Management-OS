<?php

namespace Database\Seeders;

use App\Models\CustomerInteraction;
use Illuminate\Database\Seeder;

class CustomerInteractionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CustomerInteraction::factory()->count(10)->create();
        // </architect:seed>
    }
}