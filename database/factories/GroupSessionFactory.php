<?php

namespace Database\Factories;

use App\Models\GroupSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GroupSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<GroupSession>
     */
    protected $model = GroupSession::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => \App\Models\Group::factory(),
            'course_id' => \App\Models\Course::factory(),
            'teacher_id' => \App\Models\Employee::factory(),
            'start_on' => $this->faker->dateTime(),
            'end_on' => $this->faker->dateTime(),
            'status' => $this->faker->word(),
            'identifier' => $this->faker->word()
        ];
    }
}