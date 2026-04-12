<?php

namespace Database\Factories;

use App\Models\Competitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompetitorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Competitor>
     */
    protected $model = Competitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(),
            'site' => $this->faker->word(),
            'instagram' => $this->faker->word(),
            'facebook' => $this->faker->word(),
            'linkedin' => $this->faker->word(),
            'tiktok' => $this->faker->word(),
            'locations' => $this->faker->word(),
        ];
    }
}