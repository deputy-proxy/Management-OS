<?php

namespace Database\Factories;

use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MeetingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Meeting>
     */
    protected $model = Meeting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => $this->faker->word(),
            'description' => $this->faker->paragraphs(3, true),
            'type' => $this->faker->word(),
            'status' => $this->faker->word(),
            'starts_at' => $this->faker->dateTime(),
            'ends_at' => $this->faker->dateTime(),
            'location' => $this->faker->word(),
            'organizer' => \App\Models\Employee::factory(),
            'participants' => $this->faker->word(),
        ];
    }
}