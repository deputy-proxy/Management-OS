<?php

namespace Database\Factories;

use App\Models\IndividualSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IndividualSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<IndividualSession>
     */
    protected $model = IndividualSession::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => $this->faker->word(),
            'customer_id' => \App\Models\Customer::factory(),
            'course_id' => \App\Models\Course::factory(),
            'teacher' => \App\Models\Employee::factory(),
            'starts_at' => $this->faker->dateTime(),
            'ends_at' => $this->faker->dateTime(),
            'status' => $this->faker->word(),
            'teacher_id' => \App\Models\Employee::factory()
        ];
    }
}