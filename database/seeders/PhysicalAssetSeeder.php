<?php

namespace Database\Seeders;

use App\Models\PhysicalAsset;
use Illuminate\Database\Seeder;

class PhysicalAssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        PhysicalAsset::factory()->count(10)->create();
        // </architect:seed>
    }
}