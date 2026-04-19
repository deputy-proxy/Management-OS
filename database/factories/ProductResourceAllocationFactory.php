<?php

namespace Database\Factories;

use App\Models\ProductResourceAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductResourceAllocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProductResourceAllocation>
     */
    protected $model = ProductResourceAllocation::class;

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
            'allocation_percent' => $this->faker->randomNumber(),
            'month' => $this->faker->date(),
        ];
    }
}