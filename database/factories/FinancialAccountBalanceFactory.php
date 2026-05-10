<?php

namespace Database\Factories;

use App\Models\FinancialAccountBalance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FinancialAccountBalanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FinancialAccountBalance>
     */
    protected $model = FinancialAccountBalance::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_account_id' => \App\Models\FinancialAccount::factory(),
            'current_balance' => $this->faker->randomNumber(),
            'currency' => $this->faker->word(),
            'reported_on' => $this->faker->date()
        ];
    }
}