<?php

namespace Database\Seeders;

use App\Models\FinancialAccountBalance;
use Illuminate\Database\Seeder;

class FinancialAccountBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        FinancialAccountBalance::factory()->count(10)->create();
        // </architect:seed>
    }
}