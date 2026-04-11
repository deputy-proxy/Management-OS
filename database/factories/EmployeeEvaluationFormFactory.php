<?php

namespace Database\Factories;

use App\Models\EmployeeEvaluationForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeEvaluationFormFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmployeeEvaluationForm>
     */
    protected $model = EmployeeEvaluationForm::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Employee::factory(),
            'job_description_id' => \App\Models\JobDescription::factory(),
            'criteria' => $this->faker->word()
        ];
    }
}