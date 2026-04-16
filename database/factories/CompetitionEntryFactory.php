<?php

namespace Database\Factories;

use App\Models\CompetitionEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompetitionEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CompetitionEntry>
     */
    protected $model = CompetitionEntry::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => \App\Models\Competition::factory(),
            'first_name' => $this->faker->sentence(),
            'last_name' => $this->faker->sentence(),
            'full_name' => $this->faker->sentence(),
            'email' => $this->faker->unique()->safeEmail(),
            'content' => $this->faker->paragraphs(3, true),
            'attachment' => $this->faker->word()
        ];
    }
}