<?php

namespace Database\Factories;

use App\Models\CustomerInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerInvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomerInvoice>
     */
    protected $model = CustomerInvoice::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\Customer::factory(),
            'customer_contract_id' => \App\Models\CustomerContract::factory(),
            'amount_without_vat' => $this->faker->randomFloat(2, 10, 1000),
            'vat' => $this->faker->randomNumber(),
            'month' => $this->faker->word(),
            'status' => $this->faker->word(),
            'invoice' => $this->faker->word(),
            'identifier' => $this->faker->word()
        ];
    }
}