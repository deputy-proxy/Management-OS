<?php

namespace Database\Factories;

use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NewsletterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Newsletter>
     */
    protected $model = Newsletter::class;

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
            'audience' => $this->faker->word(),
            'content' => $this->faker->paragraphs(3, true),
            'scheduled_on' => $this->faker->date(),
            'delivered_on' => $this->faker->date(),
            'status' => $this->faker->word(),
        ];
    }
}