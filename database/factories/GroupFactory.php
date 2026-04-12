<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Group>
     */
    protected $model = Group::class;

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
            'teacher_id' => \App\Models\Employee::factory(),
        ];
    }
}