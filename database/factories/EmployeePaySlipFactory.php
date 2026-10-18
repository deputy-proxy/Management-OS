<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeePaySlip;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeePaySlipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmployeePaySlip>
     */
    protected $model = EmployeePaySlip::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'items' => [],
            'manual_amount' => 0,
            'due_on' => null,
            'paid_on' => null,
            'status' => 'Open',
        ];
    }
}
