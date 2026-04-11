<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FinancialAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FinancialAccount>
     */
    protected $model = FinancialAccount::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'type' => $this->faker->word(),
            'bank' => $this->faker->word(),
            'currency' => $this->faker->word(),
            'status' => $this->faker->word(),
            'opened_on' => $this->faker->date(),
            'closed_on' => $this->faker->date()
        ];
    }
}