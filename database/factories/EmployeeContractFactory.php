<?php

namespace Database\Factories;

use App\Models\EmployeeContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'employee_id' => \App\Models\Employee::factory(),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'contract_type' => $this->faker->word(),
            'base_salary' => $this->faker->randomNumber(),
            'hourly_rate' => $this->faker->url(),
        ];
    }
}