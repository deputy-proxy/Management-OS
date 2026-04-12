<?php

namespace Database\Factories;

use App\Models\PlacementTest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlacementTestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PlacementTest>
     */
    protected $model = PlacementTest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => \App\Models\Student::factory(),
            'scheduled_on' => $this->faker->date(),
            'scheduled_at' => $this->faker->dateTime(),
            'teacher_id' => \App\Models\Employee::factory(),
            'status' => $this->faker->word(),
            'result' => $this->faker->word(),
        ];
    }
}