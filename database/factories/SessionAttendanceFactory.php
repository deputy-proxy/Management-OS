<?php

namespace Database\Factories;

use App\Models\SessionAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SessionAttendanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SessionAttendance>
     */
    protected $model = SessionAttendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => \App\Models\Session::factory(),
            'student_id' => \App\Models\Student::factory(),
        ];
    }
}