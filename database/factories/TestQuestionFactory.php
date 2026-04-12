<?php

namespace Database\Factories;

use App\Models\TestQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestQuestionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TestQuestion>
     */
    protected $model = TestQuestion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => $this->faker->paragraph(),
            'question_type' => $this->faker->word(),
            'description' => $this->faker->paragraphs(3, true),
            'correct_answer' => $this->faker->paragraph(),
            'data' => $this->faker->word(),
        ];
    }
}