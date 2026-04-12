<?php

namespace Database\Factories;

use App\Models\PaidCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PaidCampaignFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PaidCampaign>
     */
    protected $model = PaidCampaign::class;

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
            'network' => $this->faker->word(),
            'scheduled_to_start_on' => $this->faker->date(),
            'started_on' => $this->faker->date(),
            'scheduled_to_end_on' => $this->faker->date(),
            'ended_on' => $this->faker->date(),
            'budget' => $this->faker->randomNumber(),
            'status' => $this->faker->word(),
        ];
    }
}