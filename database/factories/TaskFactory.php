<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Task>
     */
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => $this->faker->word(),
            'description' => $this->faker->paragraphs(3, true),
            'assigned_to' => \App\Models\User::factory(),
            'project_id' => \App\Models\ProjectPhase::factory(),
            'project_phase_id' => \App\Models\ProjectPhase::factory(),
            'status' => $this->faker->word(),
            'due_date' => $this->faker->date(),
        ];
    }
}