<?php

namespace Database\Seeders;

use App\Models\CustomerContract;
use Illuminate\Database\Seeder;

class CustomerContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CustomerContract::factory()->count(10)->create();
        // </architect:seed>
    }
}