<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'assigned_to' => User::factory(),
            'project_id' => Project::factory(),
            'project_phase_id' => ProjectPhase::factory(),
            'status' => $this->faker->word(),
            'due_date' => $this->faker->date(),
            'created_by' => User::factory(),
        ];
    }
}
