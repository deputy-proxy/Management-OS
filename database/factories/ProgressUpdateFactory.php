<?php

namespace Database\Factories;

use App\Models\ProgressUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProgressUpdateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProgressUpdate>
     */
    protected $model = ProgressUpdate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => \App\Models\Student::factory(),
            'teacher_id' => \App\Models\Employee::factory(),
            'update' => $this->faker->paragraph(),
            'update_date' => $this->faker->date(),
        ];
    }
}