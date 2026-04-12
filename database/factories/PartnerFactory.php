<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Partner>
     */
    protected $model = Partner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(),
            'site' => $this->faker->word(),
            'contact_person_name' => $this->faker->sentence(),
            'contact_person_email' => $this->faker->unique()->safeEmail(),
            'contact_person_phone' => $this->faker->phoneNumber(),
            'collaborations' => $this->faker->word(),
        ];
    }
}