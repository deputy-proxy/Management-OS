<?php

namespace Database\Seeders;

use App\Models\CustomerInvoice;
use Illuminate\Database\Seeder;

class CustomerInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        CustomerInvoice::factory()->count(10)->create();
        // </architect:seed>
    }
}