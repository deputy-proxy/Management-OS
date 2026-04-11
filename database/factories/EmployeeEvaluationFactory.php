<?php

namespace Database\Factories;

use App\Models\EmployeeEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeEvaluationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmployeeEvaluation>
     */
    protected $model = EmployeeEvaluation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Employee::factory(),
            'criteria' => $this->faker->word(),
        ];
    }
}