<?php

namespace Database\Factories;

use App\Models\VendorContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorContractFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<VendorContract>
     */
    protected $model = VendorContract::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'document' => $this->faker->word(),
            'vendor_id' => \App\Models\Vendor::factory(),
            'contract_code' => $this->faker->word()
        ];
    }
}