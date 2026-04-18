<?php

namespace Database\Seeders;

use App\Models\DigitalAsset;
use Illuminate\Database\Seeder;

class DigitalAssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        DigitalAsset::factory()->count(10)->create();
        // </architect:seed>
    }
}