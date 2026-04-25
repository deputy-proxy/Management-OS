<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Employee>
     */
    protected $model = Employee::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => $this->faker->sentence(),
            'first_name' => $this->faker->sentence(),
            'last_name' => $this->faker->sentence(),
            'user_id' => \App\Models\User::factory(),
            'date_of_birth' => $this->faker->date()
        ];
    }
}