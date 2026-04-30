<?php

namespace Database\Factories;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductPriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProductPrice>
     */
    protected $model = ProductPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->word(),
        ];
    }
}