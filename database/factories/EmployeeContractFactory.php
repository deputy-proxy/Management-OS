<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeContractFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmployeeContract>
     */
    protected $model = EmployeeContract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'contract_type' => 'full_time',
            'base_salary' => $this->faker->randomFloat(2, 1000, 10000),
            'hourly_rate' => $this->faker->randomFloat(2, 10, 100),
            'clauses' => [],
        ];
    }
}
