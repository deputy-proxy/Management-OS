<?php

namespace Database\Factories;

use App\Models\DigitalAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DigitalAssetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<DigitalAsset>
     */
    protected $model = DigitalAsset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(),
            'type' => $this->faker->word(),
            'email_used' => $this->faker->unique()->safeEmail(),
            'username' => $this->faker->sentence(),
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'vendor_id' => \App\Models\Vendor::factory(),
            'status' => $this->faker->word(),
            'registered_on' => $this->faker->date(),
        ];
    }
}