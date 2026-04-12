<?php

namespace Database\Factories;

use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialPostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SocialPost>
     */
    protected $model = SocialPost::class;

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
            'content_template_id' => \App\Models\ContentTemplate::factory(),
            'network' => $this->faker->word(),
            'scheduled_on' => $this->faker->date(),
            'published_on' => $this->faker->date(),
            'status' => $this->faker->word(),
        ];
    }
}