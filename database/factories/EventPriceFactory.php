<?php

namespace Database\Factories;

use App\Models\EventPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EventPriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EventPrice>
     */
    protected $model = EventPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->word(),
        ];
    }
}