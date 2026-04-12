<?php

namespace Database\Factories;

use App\Models\SessionAssistance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SessionAssistanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SessionAssistance>
     */
    protected $model = SessionAssistance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_session_id' => \App\Models\GroupSession::factory(),
            'teacher_id' => \App\Models\Employee::factory(),
            'assistance_date' => $this->faker->date(),
            'status' => $this->faker->word(),
            'review' => $this->faker->word(),
        ];
    }
}