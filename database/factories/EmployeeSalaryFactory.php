<?php

namespace Database\Factories;

use App\Models\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeSalaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmployeeSalary>
     */
    protected $model = EmployeeSalary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Employee::factory(),
            'month' => $this->faker->word(),
            'items' => $this->faker->word(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'due_on' => $this->faker->date(),
            'paid_on' => $this->faker->date(),
        ];
    }
}