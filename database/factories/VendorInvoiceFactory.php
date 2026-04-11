<?php

namespace Database\Factories;

use App\Models\VendorInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorInvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<VendorInvoice>
     */
    protected $model = VendorInvoice::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => \App\Models\Vendor::factory(),
            'vendor_contract_id' => \App\Models\VendorContract::factory(),
            'amount_without_vat' => $this->faker->randomFloat(2, 10, 1000),
            'vat' => $this->faker->randomNumber(),
            'currency' => $this->faker->word(),
            'month' => $this->faker->word(),
            'status' => $this->faker->word(),
            'invoice' => $this->faker->word()
        ];
    }
}