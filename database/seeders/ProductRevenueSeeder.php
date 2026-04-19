<?php

namespace Database\Seeders;

use App\Models\ProductRevenue;
use Illuminate\Database\Seeder;

class ProductRevenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        ProductRevenue::factory()->count(10)->create();
        // </architect:seed>
    }
}