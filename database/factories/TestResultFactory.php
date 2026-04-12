<?php

namespace Database\Factories;

use App\Models\TestResult;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestResultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TestResult>
     */
    protected $model = TestResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_submission_id' => \App\Models\TestSubmission::factory(),
            'student_id' => \App\Models\Student::factory(),
            'results' => $this->faker->word(),
            'teacher_id' => \App\Models\Employee::factory(),
        ];
    }
}