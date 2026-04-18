<?php

namespace Database\Factories;

use App\Models\CampPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CampPriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CampPrice>
     */
    protected $model = CampPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'camp_id' => \App\Models\Camp::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->word(),
            'start_on' => $this->faker->date(),
            'end_on' => $this->faker->date(),
        ];
    }
}