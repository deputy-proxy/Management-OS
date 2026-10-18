<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Group;
use App\Models\Test;
use App\Models\TestSimulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TestSimulation> */
class TestSimulationFactory extends Factory
{
    protected $model = TestSimulation::class;

    public function definition(): array
    {
        return [
            'teacher_id' => Employee::factory(),
            'test_id' => Test::factory(),
            'group_id' => Group::factory(),
            'title' => $this->faker->sentence(3),
            'date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'number_of_hours' => $this->faker->randomFloat(2, 0.5, 4),
            'status' => 'completed',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
