<?php

namespace Database\Seeders;

use App\Models\EventPrice;
use Illuminate\Database\Seeder;

class EventPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        EventPrice::factory()->count(10)->create();
        // </architect:seed>
    }
}