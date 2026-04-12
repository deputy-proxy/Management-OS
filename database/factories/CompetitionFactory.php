<?php

namespace Database\Factories;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompetitionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Competition>
     */
    protected $model = Competition::class;

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
            'start_on' => $this->faker->date(),
            'end_on' => $this->faker->date(),
            'maximum_entries' => $this->faker->randomNumber(),
            'rules' => $this->faker->word(),
        ];
    }
}