<?php

namespace Database\Factories;

use App\Models\WorkshopParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WorkshopParticipantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WorkshopParticipant>
     */
    protected $model = WorkshopParticipant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workshop_id' => \App\Models\Workshop::factory(),
            'customer_id' => \App\Models\Customer::factory(),
        ];
    }
}