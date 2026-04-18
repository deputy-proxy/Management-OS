<?php

namespace Database\Factories;

use App\Models\MeetingTopci;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MeetingTopciFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MeetingTopci>
     */
    protected $model = MeetingTopci::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'meeting_id' => \App\Models\Meeting::factory(),
            'description' => $this->faker->paragraphs(3, true),
            'attachment' => $this->faker->word(),
            'outcome' => $this->faker->word(),
        ];
    }
}