<?php

namespace Database\Factories;

use App\Models\CustomerInteraction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerInteractionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomerInteraction>
     */
    protected $model = CustomerInteraction::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\Customer::factory(),
            'initiator' => $this->faker->word(),
            'reason' => $this->faker->word(),
            'description' => $this->faker->paragraphs(3, true),
            'timestamp' => $this->faker->dateTime(),
            'interaction_rating' => $this->faker->randomNumber()
        ];
    }
}