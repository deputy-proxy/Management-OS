<?php

namespace Database\Seeders;

use App\Models\WorkshopMaterial;
use Illuminate\Database\Seeder;

class WorkshopMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        WorkshopMaterial::factory()->count(10)->create();
        // </architect:seed>
    }
}