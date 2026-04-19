<?php

namespace Database\Seeders;

use App\Models\ProductCost;
use Illuminate\Database\Seeder;

class ProductCostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        ProductCost::factory()->count(10)->create();
        // </architect:seed>
    }
}