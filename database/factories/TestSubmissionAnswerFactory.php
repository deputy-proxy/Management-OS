<?php

namespace Database\Factories;

use App\Models\TestSubmissionAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestSubmissionAnswerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TestSubmissionAnswer>
     */
    protected $model = TestSubmissionAnswer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_submission_id' => \App\Models\TestSubmission::factory(),
            'test_question_id' => \App\Models\TestQuestion::factory(),
            'answer' => $this->faker->paragraph(),
            'points' => $this->faker->randomNumber(),
            'graded_by' => \App\Models\Employee::factory(),
        ];
    }
}