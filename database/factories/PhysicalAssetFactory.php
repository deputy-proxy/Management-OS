<?php

namespace Database\Factories;

use App\Models\PhysicalAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PhysicalAssetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PhysicalAsset>
     */
    protected $model = PhysicalAsset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(),
            'asset_tag' => $this->faker->word(),
            'serial_number' => $this->faker->word(),
            'category' => $this->faker->word(),
            'location' => $this->faker->word(),
            'status' => $this->faker->word(),
            'condition' => $this->faker->word(),
            'purchase_date' => $this->faker->date(),
            'purchase_price' => $this->faker->randomFloat(2, 10, 1000),
            'warranty_expiry_date' => $this->faker->date(),
        ];
    }
}