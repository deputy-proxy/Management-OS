<?php

namespace Database\Seeders;

use App\Models\VendorInvoice;
use Illuminate\Database\Seeder;

class VendorInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        VendorInvoice::factory()->count(10)->create();
        // </architect:seed>
    }
}