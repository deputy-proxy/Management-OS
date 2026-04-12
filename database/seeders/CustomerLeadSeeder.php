<?php

namespace Database\Seeders;

use App\Models\CustomerLead;
use Illuminate\Database\Seeder;

class CustomerLeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CustomerLead::factory()->count(10)->create();
        // </architect:seed>
    }
}