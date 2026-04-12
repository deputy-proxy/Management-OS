<?php

namespace Database\Factories;

use App\Models\CourseMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CourseMaterialFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CourseMaterial>
     */
    protected $model = CourseMaterial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'course_id' => \App\Models\Course::factory(),
            'description' => $this->faker->paragraphs(3, true),
            'content' => $this->faker->paragraphs(3, true),
            'added_by' => \App\Models\User::factory(),
        ];
    }
}