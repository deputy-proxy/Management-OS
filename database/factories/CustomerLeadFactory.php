<?php

namespace Database\Factories;

use App\Models\CustomerLead;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerLeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomerLead>
     */
    protected $model = CustomerLead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->sentence(),
            'middle_name' => $this->faker->sentence(),
            'last_name' => $this->faker->sentence(),
            'full_name' => $this->faker->sentence(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'source' => $this->faker->word(),
            'source_category' => 'unknown',
            'attribution_policy_version' => 'v1',
            'topic_interest' => $this->faker->word(),
            'product_interest' => $this->faker->word(),
            'status' => 'new',
            'last_contacted_at' => $this->faker->dateTime(),
        ];
    }
}
