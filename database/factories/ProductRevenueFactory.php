<?php

namespace Database\Factories;

use App\Models\ProductRevenue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductRevenueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProductRevenue>
     */
    protected $model = ProductRevenue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => \App\Models\Product::factory(),
            'customer_invoice_id' => \App\Models\CustomerInvoice::factory(),
            'month' => $this->faker->date(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}