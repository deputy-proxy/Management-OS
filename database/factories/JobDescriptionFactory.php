<?php

namespace Database\Factories;

use App\Models\JobDescription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class JobDescriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<JobDescription>
     */
    protected $model = JobDescription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_title' => $this->faker->sentence(),
            'job_level' => $this->faker->word(),
            'job_role' => $this->faker->paragraph(),
            'relevant_indicators' => $this->faker->word(),
            'document_code' => $this->faker->word(),
            'document' => $this->faker->word(),
        ];
    }
}