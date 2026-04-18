<?php

namespace Database\Factories;

use App\Models\CampParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CampParticipantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CampParticipant>
     */
    protected $model = CampParticipant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'camp_id' => \App\Models\Camp::factory(),
            'customer_id' => \App\Models\Customer::factory(),
        ];
    }
}