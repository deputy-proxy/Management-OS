<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Session> */
class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition(): array
    {
        return [
            'session_type' => 'group',
            'group_id' => Group::factory(),
            'course_id' => Course::factory(),
            'teacher_id' => Employee::factory(),
            'starts_at' => $this->faker->dateTime(),
            'ends_at' => $this->faker->dateTime(),
            'status' => 'scheduled',
            'identifier' => $this->faker->word(),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (): array => [
            'session_type' => 'individual',
            'group_id' => null,
            'customer_id' => Customer::factory(),
        ]);
    }
}
