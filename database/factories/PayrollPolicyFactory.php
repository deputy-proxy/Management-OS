<?php

namespace Database\Factories;

use App\Models\PayrollPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollPolicy> */
class PayrollPolicyFactory extends Factory
{
    protected $model = PayrollPolicy::class;

    public function definition(): array
    {
        return [
            'policy_key' => 'synthetic_employee_policy',
            'name' => 'Synthetic employee policy',
            'description' => 'Synthetic test configuration only.',
            'effective_from' => now()->toDateString(),
            'effective_until' => null,
            'parameters' => [],
            'required_parameters' => [],
            'calculation_order' => [],
            'rounding_mode' => 'half_up',
            'rounding_precision' => 2,
        ];
    }
}
