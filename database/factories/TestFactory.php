<?php

namespace Database\Factories;

use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Test>
     */
    protected $model = Test::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(3, true),
            'topic' => $this->faker->word(),
            'level' => $this->faker->word(),
            'questions' => $this->faker->word(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}