<?php

namespace Database\Factories;

use App\Models\ProductResource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductResourceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProductResource>
     */
    protected $model = ProductResource::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'month' => $this->faker->date(),
            'allocated_percentage' => $this->faker->randomNumber()
        ];
    }
}