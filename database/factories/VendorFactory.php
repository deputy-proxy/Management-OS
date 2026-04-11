<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Vendor>
     */
    protected $model = Vendor::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(),
            'vendor_category_id' => \App\Models\VendorCategory::factory(),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'website' => $this->faker->url()
        ];
    }
}