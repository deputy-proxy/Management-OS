<?php

namespace Database\Seeders;

use App\Models\VendorContract;
use Illuminate\Database\Seeder;

class VendorContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        VendorContract::factory()->count(10)->create();
        // </architect:seed>
    }
}