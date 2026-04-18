<?php

namespace Database\Factories;

use App\Models\Camp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CampFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Camp>
     */
    protected $model = Camp::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(3, true),
            'location' => $this->faker->word(),
            'start_on' => $this->faker->date(),
            'end_on' => $this->faker->date(),
            'camp_price' => $this->faker->randomFloat(2, 10, 1000),
            'camp_price_id' => \App\Models\CampPrice::factory()
        ];
    }
}