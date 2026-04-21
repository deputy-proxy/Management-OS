<?php

namespace Database\Factories;

use App\Models\TestSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestSubmissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TestSubmission>
     */
    protected $model = TestSubmission::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_id' => \App\Models\Test::factory(),
            'student_id' => \App\Models\Student::factory(),
            'answers' => $this->faker->word(),
            'submission_timestamp' => $this->faker->dateTime(),
            'status' => $this->faker->word(),
            'score' => $this->faker->randomNumber()
        ];
    }
}