<?php

namespace Database\Factories;

use App\Models\ContentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ContentTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ContentTemplate>
     */
    protected $model = ContentTemplate::class;

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
            'template_for' => $this->faker->word(),
            'link' => $this->faker->word(),
            'last_revision' => $this->faker->date(),
        ];
    }
}