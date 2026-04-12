<?php

namespace Database\Seeders;

use App\Models\WorkshopPrice;
use Illuminate\Database\Seeder;

class WorkshopPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        WorkshopPrice::factory()->count(10)->create();
        // </architect:seed>
    }
}