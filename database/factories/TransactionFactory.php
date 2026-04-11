<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Transaction>
     */
    protected $model = Transaction::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_account_id' => \App\Models\FinancialAccount::factory(),
            'vendor_invoice_id' => \App\Models\VendorInvoice::factory(),
            'transaction_type' => $this->faker->word()
        ];
    }
}