<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Student>
     */
    protected $model = Student::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->sentence(),
            'middle_name' => $this->faker->sentence(),
            'last_name' => $this->faker->sentence(),
            'gender' => $this->faker->word(),
            'date_of_birth' => $this->faker->date(),
            'customer_id' => \App\Models\Customer::factory(),
            'phone_number' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'full_name' => $this->faker->sentence()
        ];
    }
}