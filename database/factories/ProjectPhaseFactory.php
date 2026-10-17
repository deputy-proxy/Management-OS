<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectPhaseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProjectPhase>
     */
    protected $model = ProjectPhase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'project_id' => Project::factory(),
            'description' => $this->faker->paragraphs(3, true),
            'start_on' => $this->faker->date(),
            'end_on' => $this->faker->date(),
            'order' => $this->faker->randomNumber(),
        ];
    }
}
