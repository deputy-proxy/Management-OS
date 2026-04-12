<?php

namespace Database\Factories;

use App\Models\WorkshopMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WorkshopMaterialFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WorkshopMaterial>
     */
    protected $model = WorkshopMaterial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'workshop_id' => \App\Models\Workshop::factory(),
            'description' => $this->faker->paragraphs(3, true),
            'content' => $this->faker->paragraphs(3, true),
            'added_by' => \App\Models\User::factory(),
        ];
    }
}