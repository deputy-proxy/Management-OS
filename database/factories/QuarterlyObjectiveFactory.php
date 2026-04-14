<?php

namespace Database\Factories;

use App\Models\QuarterlyObjective;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class QuarterlyObjectiveFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<QuarterlyObjective>
     */
    protected $model = QuarterlyObjective::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Employee::factory(),
            'objectives' => $this->faker->word(),
            'description' => $this->faker->paragraphs(3, true),
            'quarter' => $this->faker->word(),
            'status' => $this->faker->word(),
        ];
    }
}