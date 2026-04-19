<?php

namespace Database\Factories;

use App\Models\ProductCost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductCostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProductCost>
     */
    protected $model = ProductCost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => \App\Models\Product::factory(),
            'product_resource_id' => \App\Models\ProductResource::factory(),
            'month' => $this->faker->date(),
            'allocation_percent' => $this->faker->randomNumber(),
            'resource_amount' => $this->faker->randomFloat(2, 10, 1000),
            'allocated_amount' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}