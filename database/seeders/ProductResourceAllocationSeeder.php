<?php

namespace Database\Seeders;

use App\Models\ProductResourceAllocation;
use Illuminate\Database\Seeder;

class ProductResourceAllocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        ProductResourceAllocation::factory()->count(10)->create();
        // </architect:seed>
    }
}