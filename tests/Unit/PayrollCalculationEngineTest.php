<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollCalculationEngine;
use PHPUnit\Framework\TestCase;

class PayrollCalculationEngineTest extends TestCase
{
    public function test_calculates_configured_earnings_allowances_percentage_and_deductions_deterministically(): void
    {
        [$input, $policy] = $this->fixtures();
        $engine = new PayrollCalculationEngine;
        $first = $engine->calculate($input, $policy);
        $second = $engine->calculate($input, $policy);

        $this->assertSame('calculated', $first['status']);
        $this->assertSame('4500.00', $first['gross_amount']);
        $this->assertSame('200.00', $first['deductions_amount']);
        $this->assertSame('4300.00', $first['net_amount']);
        $this->assertSame('RON', $first['currency_code']);
        $this->assertCount(5, $first['line_items']);
        $this->assertSame('employer_contribution', $first['line_items'][4]['item_type']);
        $this->assertSame('percentage_of', $first['line_items'][2]['calculation_method']);
        $this->assertSame('synthetic-policy', $first['line_items'][2]['calculation_snapshot']['policy_key']);
        $this->assertSame($first, $second);
    }

    public function test_rounding_modes_are_applied_exactly_at_the_configured_precision(): void
    {
        [$input, $policy] = $this->fixtures();
        $input['payroll_input']['base_amount'] = '10.05';
        $policy['parameters'] = ['base' => [
            'type' => 'input_amount', 'input_field' => 'base_amount', 'expected_salary_basis' => 'monthly',
            'item_type' => 'earning', 'label' => 'Synthetic rounding case',
        ]];
        $policy['required_parameters'] = ['base'];
        $policy['calculation_order'] = ['base'];
        $policy['rounding_precision'] = 1;

        foreach ([
            'half_up' => '10.1',
            'half_down' => '10.0',
            'half_even' => '10.0',
            'half_odd' => '10.1',
        ] as $mode => $expected) {
            $policy['rounding_mode'] = $mode;
            $result = (new PayrollCalculationEngine)->calculate($input, $policy);
            $this->assertSame('calculated', $result['status'], $mode);
            $this->assertSame($expected, $result['gross_amount'], $mode);
        }
    }

    public function test_fixed_amounts_require_an_explicit_matching_currency(): void
    {
        [$input, $policy] = $this->fixtures();
        $policy['parameters'] = ['fixed' => json_encode([
            'type' => 'fixed_amount', 'amount' => '125.50', 'currency_code' => 'RON', 'amount_basis' => 'period_amount',
            'item_type' => 'earning', 'label' => 'Synthetic fixed earning',
        ], JSON_THROW_ON_ERROR)];
        $policy['required_parameters'] = ['fixed'];
        $policy['calculation_order'] = ['fixed'];
        $result = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('calculated', $result['status']);
        $this->assertSame('125.50', $result['gross_amount']);

        $policy['parameters']['fixed'] = json_encode([
            'type' => 'fixed_amount', 'amount' => '125.50', 'currency_code' => 'EUR', 'amount_basis' => 'period_amount',
            'item_type' => 'earning', 'label' => 'Synthetic fixed earning',
        ], JSON_THROW_ON_ERROR);
        $blocked = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('fixed_amount_currency_mismatch', array_column($blocked['validation_messages'], 'code'));
        $this->assertSame([], $blocked['line_items']);
    }

    public function test_annual_salary_requires_an_explicit_period_divisor(): void
    {
        [$input, $policy] = $this->fixtures();
        $input['payroll_input']['salary_basis'] = 'annual';
        $input['payroll_input']['base_amount'] = '120000.00';
        $policy['parameters'] = ['base' => [
            'type' => 'input_amount', 'input_field' => 'base_amount', 'expected_salary_basis' => 'annual',
            'item_type' => 'earning', 'label' => 'Synthetic annual base',
        ]];
        $policy['required_parameters'] = ['base'];
        $policy['calculation_order'] = ['base'];

        $blocked = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('period_conversion_missing', array_column($blocked['validation_messages'], 'code'));

        $policy['parameters']['base']['period_divisor'] = '12';
        $calculated = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('calculated', $calculated['status']);
        $this->assertSame('10000.00', $calculated['gross_amount']);
    }

    public function test_missing_configuration_and_invalid_decimal_values_block_without_partial_results(): void
    {
        [$input, $policy] = $this->fixtures();
        $policy['required_parameters'][] = 'unconfigured_rate';
        $blocked = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('unconfigured_rate', $blocked['unresolved_configuration_gaps']);
        $this->assertSame([], $blocked['line_items']);
        $this->assertNull($blocked['net_amount']);

        [$input, $policy] = $this->fixtures();
        $policy['parameters']['deduction']['rate_percent'] = 12.5;
        $blocked = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('percentage_rate_invalid', array_column($blocked['validation_messages'], 'code'));
        $this->assertSame([], $blocked['line_items']);
    }

    public function test_contract_route_can_override_its_own_parameters_and_calculation_order(): void
    {
        [$input, $policy] = $this->fixtures();
        $policy['parameters'] = [];
        $policy['required_parameters'] = [];
        $policy['calculation_order'] = [];
        $policy['contract_rules']['full_time'] = json_encode([
            'supported_salary_bases' => ['monthly'],
            'parameters' => ['contract_base' => [
                'type' => 'fixed_amount', 'amount' => '777.25', 'amount_basis' => 'period_amount',
                'currency_code' => 'RON', 'item_type' => 'earning', 'label' => 'Contract-specific configured earning',
            ]],
            'required_parameters' => ['contract_base'],
            'calculation_order' => ['contract_base'],
        ], JSON_THROW_ON_ERROR);

        $result = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('calculated', $result['status']);
        $this->assertSame('777.25', $result['gross_amount']);
        $this->assertSame('contract_base', $result['line_items'][0]['item_code']);
        $this->assertSame('contract_base', $result['line_items'][0]['calculation_snapshot']['formula']['parameter']);
    }

    public function test_unconfigured_contract_types_are_not_routed_to_a_default_policy(): void
    {
        [$input, $policy] = $this->fixtures();
        $input['contract']['contract_type'] = 'contractor';
        $policy['contract_rules']['contractor'] = ['supported_salary_bases' => ['monthly']];
        $result = (new PayrollCalculationEngine)->calculate($input, $policy);

        $this->assertSame('blocked', $result['status']);
        $this->assertContains('contract_type_separate_workflow', array_column($result['validation_messages'], 'code'));
        $this->assertSame([], $result['line_items']);
    }

    public function test_unsupported_proration_rules_block_even_when_the_period_is_full(): void
    {
        [$input, $policy] = $this->fixtures();
        $policy['proration_rule'] = ['method' => 'working_days'];
        $result = (new PayrollCalculationEngine)->calculate($input, $policy);

        $this->assertSame('blocked', $result['status']);
        $this->assertContains('proration_rule_unsupported', array_column($result['validation_messages'], 'code'));
    }

    public function test_partial_periods_require_an_explicit_calendar_day_proration_rule(): void
    {
        [$input, $policy] = $this->fixtures();
        $input['payroll_input']['segment_end'] = '2026-01-15';
        $input['payroll_input']['segment_days'] = 15;
        $input['contract_active_period']['end'] = '2026-01-15';
        $input['contract_active_period']['days'] = 15;
        $blocked = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('proration_rule_missing', array_column($blocked['validation_messages'], 'code'));

        $policy['proration_rule'] = ['method' => 'calendar_days'];
        $calculated = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('calculated', $calculated['status']);
        $this->assertSame('1935.48', $calculated['line_items'][0]['amount']);
        $this->assertSame('48.39', $calculated['line_items'][1]['amount']);
    }

    public function test_decimal_storage_overflow_blocks_the_whole_result(): void
    {
        [$input, $policy] = $this->fixtures();
        $input['payroll_input']['base_amount'] = '9999999999999.999999';
        $policy['parameters']['bonus']['rate_percent'] = '200';

        $result = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('calculation_amount_overflow', array_column($result['validation_messages'], 'code'));
        $this->assertSame([], $result['line_items']);
        $this->assertNull($result['gross_amount']);
        $this->assertNull($result['net_amount']);
    }

    public function test_deductions_exceeding_earnings_and_unsupported_salary_bases_are_blocked(): void
    {
        [$input, $policy] = $this->fixtures();
        $policy['parameters']['deduction']['rate_percent'] = '150';
        $blocked = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('deductions_exceed_gross', array_column($blocked['validation_messages'], 'code'));
        $this->assertNull($blocked['gross_amount']);

        [$input, $policy] = $this->fixtures();
        $input['payroll_input']['salary_basis'] = 'hourly';
        $policy['parameters']['base_pay']['expected_salary_basis'] = 'hourly';
        $policy['contract_rules']['full_time']['supported_salary_bases'][] = 'hourly';
        $blocked = (new PayrollCalculationEngine)->calculate($input, $policy);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('salary_basis_not_supported_for_period', array_column($blocked['validation_messages'], 'code'));
    }

    /** @return array{array<string,mixed>, array<string,mixed>} */
    private function fixtures(): array
    {
        $parameters = [
            'base_pay' => [
                'type' => 'input_amount', 'input_field' => 'base_amount', 'expected_salary_basis' => 'monthly',
                'item_type' => 'earning', 'label' => 'Synthetic base pay',
            ],
            'transport' => [
                'type' => 'input_allowance', 'allowance_code' => 'synthetic_transport',
                'item_type' => 'earning', 'label' => 'Synthetic transport allowance', 'amount_basis' => 'monthly_amount',
            ],
            'bonus' => [
                'type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '10',
                'item_type' => 'earning', 'label' => 'Synthetic configured bonus',
            ],
            'deduction' => [
                'type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '5',
                'item_type' => 'deduction', 'label' => 'Synthetic configured deduction',
            ],
            'employer_cost' => [
                'type' => 'fixed_amount', 'amount' => '25.00', 'currency_code' => 'RON', 'amount_basis' => 'period_amount',
                'item_type' => 'employer_contribution', 'label' => 'Synthetic employer-side cost',
            ],
        ];

        return [[
            'contract' => ['contract_type' => 'full_time'],
            'contract_active_period' => ['start' => '2026-01-01', 'end' => '2026-01-31', 'days' => 31, 'period_days' => 31],
            'payroll_input' => [
                'id' => 1, 'segment_start' => '2026-01-01', 'segment_end' => '2026-01-31', 'segment_days' => 31, 'period_days' => 31, 'contract_active_days' => 31,
                'input_status' => 'verified', 'salary_basis' => 'monthly', 'base_amount' => '4000.00',
                'currency_code' => 'RON',
                'allowance_inputs' => [['code' => 'synthetic_transport', 'amount' => '100.00', 'currency_code' => 'RON']],
            ],
        ], [
            'policy_key' => 'synthetic-policy', 'version' => 7, 'status' => 'active',
            'parameters' => $parameters, 'required_parameters' => array_keys($parameters),
            'calculation_order' => array_keys($parameters),
            'contract_rules' => ['full_time' => ['supported_salary_bases' => ['monthly', 'annual']]],
            'proration_rule' => null, 'rounding_mode' => 'half_up', 'rounding_precision' => 2,
        ]];
    }
}
