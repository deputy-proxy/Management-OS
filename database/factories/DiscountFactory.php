<?php

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DiscountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Discount>
     */
    protected $model = Discount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applies_to_type' => $this->faker->word(),
            'applies_to_item' => $this->faker->randomNumber(),
            'value' => $this->faker->randomNumber(),
            'type' => $this->faker->word(),
            'start_on' => $this->faker->date(),
            'end_on' => $this->faker->date(),
        ];
    }
}