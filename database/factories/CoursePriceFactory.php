<?php

namespace Database\Factories;

use App\Models\CoursePrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CoursePriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CoursePrice>
     */
    protected $model = CoursePrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => \App\Models\Course::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'start_on' => $this->faker->date(),
            'end_on' => $this->faker->date(),
        ];
    }
}