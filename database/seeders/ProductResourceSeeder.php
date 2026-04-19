<?php

namespace Database\Seeders;

use App\Models\ProductResource;
use Illuminate\Database\Seeder;

class ProductResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        ProductResource::factory()->count(10)->create();
        // </architect:seed>
    }
}