<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Event>
     */
    protected $model = Event::class;

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
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'start_time' => $this->faker->dateTime(),
            'end_time' => $this->faker->dateTime(),
            'featured_image' => $this->faker->word(),
        ];
    }
}
