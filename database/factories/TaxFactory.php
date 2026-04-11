<?php

namespace Database\Factories;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TaxFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Tax>
     */
    protected $model = Tax::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => $this->faker->word(),
            'month' => $this->faker->word(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'status' => $this->faker->word(),
            'due_on' => $this->faker->date(),
            'paid_on' => $this->faker->date(),
        ];
    }
}