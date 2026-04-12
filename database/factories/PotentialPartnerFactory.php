<?php

namespace Database\Factories;

use App\Models\PotentialPartner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PotentialPartnerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PotentialPartner>
     */
    protected $model = PotentialPartner::class;

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
            'type' => $this->faker->word(),
            'benefits_for_us' => $this->faker->paragraph(),
            'benefits_for_them' => $this->faker->paragraph(),
            'last_contact' => $this->faker->date(),
        ];
    }
}