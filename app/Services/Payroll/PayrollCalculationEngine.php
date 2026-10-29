<?php

namespace App\Services\Payroll;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Deterministic arithmetic over explicit, versioned policy data. No statutory rates are embedded. */
class PayrollCalculationEngine
{
    public const VERSION = 'payroll-decimal-v1';

    private const ITEM_TYPES = ['earning', 'deduction', 'employer_contribution', 'informational'];

    /** @return array<string, mixed> */
    public function calculate(array $inputSnapshot, array $policySnapshot): array
    {
        $messages = [];
        $gaps = [];
        $input = $inputSnapshot['payroll_input'] ?? null;
        $parameters = $policySnapshot['parameters'] ?? null;
        $order = $policySnapshot['calculation_order'] ?? null;
        $required = $policySnapshot['required_parameters'] ?? null;
        $currency = is_array($input) && is_string($input['currency_code'] ?? null) ? $input['currency_code'] : null;
        $precision = $policySnapshot['rounding_precision'] ?? null;
        $modeName = is_string($policySnapshot['rounding_mode'] ?? null) ? strtolower($policySnapshot['rounding_mode']) : null;
        $roundingMode = $this->roundingMode($modeName);
        $reasons = [];

        if (! is_array($input) || ($input['input_status'] ?? null) !== 'verified') {
            $this->message($messages, 'payroll_input_not_verified', 'An explicit verified payroll input snapshot is required.');
            $reasons[] = 'payroll_input_not_verified';
        }
        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            $this->message($messages, 'currency_code_invalid', 'An explicit uppercase three-letter currency code is required.');
            $reasons[] = 'currency_code_invalid';
        }
        if (! in_array($policySnapshot['status'] ?? null, ['active', 'retired'], true)) {
            $this->message($messages, 'policy_not_activated', 'The selected policy version is not activated.');
            $reasons[] = 'policy_not_activated';
        }
        if (! is_array($parameters)) {
            $this->message($messages, 'policy_parameters_invalid', 'Policy parameters must be a structured object.');
            $reasons[] = 'policy_parameters_invalid';
            $parameters = [];
        }
        if (! is_array($required) || ! array_is_list($required)) {
            $this->message($messages, 'required_parameters_invalid', 'Required policy parameters must be an explicit list.');
            $reasons[] = 'required_parameters_invalid';
            $required = [];
        }
        foreach ($required as $path) {
            if (! is_string($path) || trim($path) === '') {
                $this->message($messages, 'required_parameter_path_invalid', 'Required parameter paths must be non-empty strings.');
                $reasons[] = 'required_parameter_path_invalid';

                continue;
            }
            $value = $this->parameter($parameters, $path);
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $gaps[] = $path;
            }
        }
        if ($gaps !== []) {
            $this->message($messages, 'policy_configuration_incomplete', 'Missing required policy configuration: '.implode(', ', $gaps).'.');
            $reasons[] = 'policy_configuration_incomplete';
        }
        if (! is_int($precision) || $precision < 0 || $precision > 6 || ($roundingMode === null && $modeName !== 'half_odd')) {
            $this->message($messages, 'rounding_configuration_invalid', 'A supported rounding mode and precision from 0 to 6 are required.');
            $reasons[] = 'rounding_configuration_invalid';
        }
        if (! is_array($order) || ! array_is_list($order) || $order === []) {
            $this->message($messages, 'calculation_order_missing', 'A non-empty calculation order is required.');
            $reasons[] = 'calculation_order_missing';
            $order = [];
        } elseif (count($order) !== count(array_unique($order))) {
            $this->message($messages, 'calculation_order_duplicate', 'Each calculation step must appear only once in the order.');
            $reasons[] = 'calculation_order_duplicate';
        }
        if ($messages !== []) {
            return $this->blocked($messages, $reasons, $gaps, $currency, $modeName, is_int($precision) ? $precision : null);
        }

        if (! is_array($input)) {
            return $this->blocked($messages, $reasons, $gaps, $currency, $modeName, is_int($precision) ? $precision : null);
        }
        /** @var int $precision */
        $computed = [];
        $lines = [];
        foreach ($order as $code) {
            if (! is_string($code) || trim($code) === '') {
                $this->message($messages, 'calculation_step_invalid', 'Each calculation step must be a non-empty parameter path.');

                continue;
            }
            if (mb_strlen($code) > 128) {
                $this->message($messages, 'calculation_step_too_long', 'Calculation parameter paths cannot exceed 128 characters.', $code);

                continue;
            }
            $definition = $this->parameter($parameters, $code);
            if (is_string($definition)) {
                $decoded = json_decode($definition, true);
                if (is_array($decoded)) {
                    $definition = $decoded;
                }
            }
            if (! is_array($definition)) {
                $this->message($messages, 'parameter_definition_invalid', 'Each step must be a JSON object describing a supported calculation.', $code);

                continue;
            }
            $type = $definition['type'] ?? null;
            $itemType = $definition['item_type'] ?? null;
            if (! is_string($type) || ! in_array($type, ['input_amount', 'fixed_amount', 'input_allowance', 'percentage_of'], true)) {
                $this->message($messages, 'calculation_type_unsupported', 'Supported types are input_amount, fixed_amount, input_allowance, and percentage_of.', $code);

                continue;
            }
            if (! is_string($itemType) || ! in_array($itemType, self::ITEM_TYPES, true)) {
                $this->message($messages, 'item_type_invalid', 'Every rule must explicitly declare a supported item_type.', $code);

                continue;
            }
            $label = is_string($definition['label'] ?? null) && trim($definition['label']) !== '' ? mb_substr(trim($definition['label']), 0, 255) : $code;
            $amount = null;
            $inputValue = null;
            $ratePercent = null;
            $baseItemCode = null;
            $formula = ['parameter' => $code, 'definition' => $definition];
            if ($type === 'input_amount') {
                $field = $definition['input_field'] ?? null;
                $expectedBasis = $definition['expected_salary_basis'] ?? null;
                $basis = $input['salary_basis'] ?? null;
                $inputValue = $this->decimal($input['base_amount'] ?? null);
                if ($field !== 'base_amount' || ! is_string($expectedBasis) || $expectedBasis !== $basis) {
                    $this->message($messages, 'salary_basis_mismatch', 'The input field and salary basis must exactly match the explicit policy definition.', $code);

                    continue;
                }
                if ($inputValue === null || $inputValue->compareTo(BigDecimal::zero()) < 0) {
                    $this->message($messages, 'base_amount_invalid', 'An explicit non-negative base amount is required.', $code);

                    continue;
                }
                if ($basis === 'monthly') {
                    if (array_key_exists('period_divisor', $definition)) {
                        $this->message($messages, 'period_conversion_invalid', 'Monthly amounts must not use a period divisor.', $code);

                        continue;
                    }
                    $amount = $inputValue;
                } elseif ($basis === 'annual') {
                    $divisor = $this->decimal($definition['period_divisor'] ?? null);
                    if ($divisor === null || $divisor->compareTo(BigDecimal::zero()) <= 0) {
                        $this->message($messages, 'period_conversion_missing', 'Annual amounts require an explicit positive period_divisor.', $code);

                        continue;
                    }
                    $amount = $inputValue->dividedBy((string) $divisor, 12, RoundingMode::HalfUp);
                    $formula['period_divisor'] = (string) $divisor;
                } else {
                    $this->message($messages, 'salary_basis_not_supported', 'Hourly and daily bases require period-specific quantities that are not present in this snapshot.', $code);

                    continue;
                }
                $formula['input_field'] = $field;
                $formula['expected_salary_basis'] = $expectedBasis;
                $formula['input_value'] = (string) $inputValue;
            } elseif ($type === 'fixed_amount') {
                $amount = $this->decimal($definition['amount'] ?? null);
                $configuredCurrency = $definition['currency_code'] ?? null;
                if ($amount === null || $amount->compareTo(BigDecimal::zero()) < 0) {
                    $this->message($messages, 'fixed_amount_invalid', 'Fixed amounts must be explicit non-negative decimal strings or integers.', $code);

                    continue;
                }
                if (! is_string($configuredCurrency) || $configuredCurrency !== $currency) {
                    $this->message($messages, 'fixed_amount_currency_mismatch', 'Fixed amount currency must match the input currency.', $code);

                    continue;
                }
                $inputValue = $amount;
                $formula['configured_amount'] = (string) $amount;
                $formula['configured_currency_code'] = $configuredCurrency;
            } elseif ($type === 'input_allowance') {
                $allowanceCode = $definition['allowance_code'] ?? null;
                $allowances = $input['allowance_inputs'] ?? null;
                if (! is_string($allowanceCode) || ! is_array($allowances) || ! array_is_list($allowances)) {
                    $this->message($messages, 'allowance_reference_invalid', 'An explicit allowance code and input list are required.', $code);

                    continue;
                }
                $matches = array_values(array_filter($allowances, fn (mixed $allowance): bool => is_array($allowance) && ($allowance['code'] ?? null) === $allowanceCode));
                if (count($matches) !== 1) {
                    $this->message($messages, 'allowance_reference_ambiguous', 'The allowance code must match exactly one explicit input allowance.', $code);

                    continue;
                }
                $allowance = $matches[0];
                $amount = $this->decimal($allowance['amount'] ?? null);
                if ($amount === null || $amount->compareTo(BigDecimal::zero()) < 0) {
                    $this->message($messages, 'allowance_amount_invalid', 'The allowance must have an explicit non-negative amount.', $code);

                    continue;
                }
                if (($allowance['currency_code'] ?? null) !== $currency) {
                    $this->message($messages, 'allowance_currency_mismatch', 'Allowance currency must match the input currency.', $code);

                    continue;
                }
                $inputValue = $amount;
                $formula['allowance_code'] = $allowanceCode;
                $formula['input_value'] = (string) $amount;
            } else {
                $baseItemCode = $definition['base_item_code'] ?? null;
                $ratePercent = $this->decimal($definition['rate_percent'] ?? null);
                if (! is_string($baseItemCode) || ! isset($computed[$baseItemCode])) {
                    $this->message($messages, 'percentage_base_unavailable', 'Percentage rules must reference a previously calculated line.', $code);

                    continue;
                }
                if ($ratePercent === null || $ratePercent->compareTo(BigDecimal::zero()) < 0) {
                    $this->message($messages, 'percentage_rate_invalid', 'Percentage rates must be explicit non-negative decimal strings or integers.', $code);

                    continue;
                }
                $inputValue = $computed[$baseItemCode]['amount'];
                $amount = $inputValue->multipliedBy($ratePercent)->dividedBy('100', 12, RoundingMode::HalfUp);
                $formula['base_item_code'] = $baseItemCode;
                $formula['base_amount'] = (string) $inputValue;
                $formula['rate_percent'] = (string) $ratePercent;
            }

            $amount = $this->round($amount, $precision, $modeName, $roundingMode);
            if (! $this->fitsMoneyColumn($amount)) {
                $this->message($messages, 'calculation_amount_overflow', 'Calculated line exceeds the supported 19-digit decimal storage range.', $code);

                continue;
            }
            $line = [
                'line_number' => count($lines) + 1,
                'item_code' => $code,
                'item_type' => $itemType,
                'label' => $label,
                'calculation_method' => $type,
                'source_parameter' => $code,
                'base_item_code' => $baseItemCode,
                'input_value' => $this->format($inputValue, 6),
                'rate_percent' => $ratePercent === null ? null : $this->format($ratePercent, 6),
                'amount' => $this->format($amount, $precision),
                'currency_code' => $currency,
                'rounding_mode' => $modeName,
                'rounding_precision' => $precision,
                'calculation_snapshot' => [
                    'calculation_version' => self::VERSION,
                    'policy_key' => $policySnapshot['policy_key'] ?? null,
                    'policy_version' => $policySnapshot['version'] ?? null,
                    'formula' => $formula,
                    'rounding_mode' => $modeName,
                    'rounding_precision' => $precision,
                ],
            ];
            $lines[] = $line;
            $computed[$code] = ['amount' => $amount, 'item_type' => $itemType];
        }
        if ($messages !== []) {
            return $this->blocked($messages, ['calculation_validation_failed'], $gaps, $currency, $modeName, $precision);
        }

        $hasEarning = false;
        $gross = BigDecimal::zero();
        $deductions = BigDecimal::zero();
        foreach ($computed as $entry) {
            if ($entry['item_type'] === 'earning') {
                $hasEarning = true;
                $gross = $gross->plus($entry['amount']);
            } elseif ($entry['item_type'] === 'deduction') {
                $deductions = $deductions->plus($entry['amount']);
            }
        }
        if (! $hasEarning) {
            $this->message($messages, 'earning_line_missing', 'At least one configured earning line is required.');
        }
        $gross = $this->round($gross, $precision, $modeName, $roundingMode);
        $deductions = $this->round($deductions, $precision, $modeName, $roundingMode);
        if (! $this->fitsMoneyColumn($gross) || ! $this->fitsMoneyColumn($deductions)) {
            $this->message($messages, 'calculation_total_overflow', 'Calculated totals exceed the supported 19-digit decimal storage range.');
        }
        $net = $gross->minus($deductions);
        if ($net->compareTo(BigDecimal::zero()) < 0) {
            $this->message($messages, 'deductions_exceed_gross', 'Configured deductions exceed configured earnings; the result is blocked.');
        }
        if ($messages !== []) {
            return $this->blocked($messages, ['calculation_validation_failed'], $gaps, $currency, $modeName, $precision);
        }

        return [
            'status' => 'calculated',
            'calculation_version' => self::VERSION,
            'blocked_reasons' => [],
            'validation_messages' => [],
            'unresolved_configuration_gaps' => [],
            'line_items' => $lines,
            'gross_amount' => $this->format($gross, $precision),
            'deductions_amount' => $this->format($deductions, $precision),
            'net_amount' => $this->format($net, $precision),
            'currency_code' => $currency,
            'rounding_mode' => $modeName,
            'rounding_precision' => $precision,
        ];
    }

    private function parameter(array $parameters, string $path): mixed
    {
        if (array_key_exists($path, $parameters)) {
            return $parameters[$path];
        }
        $value = $parameters;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function decimal(mixed $value): ?BigDecimal
    {
        if ((! is_string($value) && ! is_int($value)) || preg_match('/^-?\d+(?:\.\d+)?$/D', (string) $value) !== 1) {
            return null;
        }
        $parts = explode('.', ltrim((string) $value, '-'), 2);
        if (strlen(ltrim($parts[0], '0')) > 13 || strlen($parts[1] ?? '') > 6) {
            return null;
        }
        try {
            return BigDecimal::of((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function roundingMode(?string $mode): ?RoundingMode
    {
        return match ($mode) {
            'half_up' => RoundingMode::HalfUp,
            'half_down' => RoundingMode::HalfDown,
            'half_even' => RoundingMode::HalfEven,
            'half_odd' => null,
            default => null,
        };
    }

    private function round(BigDecimal $value, int $precision, ?string $modeName, ?RoundingMode $mode): BigDecimal
    {
        if ($modeName !== 'half_odd') {
            return $value->toScale($precision, $mode ?? RoundingMode::HalfUp);
        }
        $down = $value->toScale($precision, RoundingMode::Down);
        $distance = $value->minus($down)->abs();
        $halfUnit = BigDecimal::of('0.'.str_repeat('0', $precision).'5');
        $comparison = $distance->compareTo($halfUnit);
        if ($comparison < 0) {
            return $down;
        }
        if ($comparison > 0) {
            return $value->toScale($precision, RoundingMode::HalfUp);
        }
        $lastDigit = (int) substr((string) $down->toScale($precision), -1);

        return $lastDigit % 2 === 1 ? $down : $value->toScale($precision, RoundingMode::Up);
    }

    private function fitsMoneyColumn(BigDecimal $amount): bool
    {
        return $amount->compareTo(BigDecimal::of('9999999999999.999999')) <= 0;
    }

    private function format(BigDecimal $value, int $precision): string
    {
        return (string) $value->toScale($precision, RoundingMode::HalfUp);
    }

    /** @param list<array{code:string,message:string,parameter?:string}> $messages */
    private function message(array &$messages, string $code, string $message, ?string $parameter = null): void
    {
        $entry = ['code' => $code, 'message' => $message];
        if ($parameter !== null) {
            $entry['parameter'] = $parameter;
        }
        $messages[] = $entry;
    }

    /** @return array<string, mixed> */
    private function blocked(array $messages, array $reasons, array $gaps, ?string $currency, ?string $mode, ?int $precision): array
    {
        return [
            'status' => 'blocked',
            'calculation_version' => self::VERSION,
            'blocked_reasons' => array_values(array_unique($reasons)),
            'validation_messages' => $messages,
            'unresolved_configuration_gaps' => array_values(array_unique($gaps)),
            'line_items' => [],
            'gross_amount' => null,
            'deductions_amount' => null,
            'net_amount' => null,
            'currency_code' => $currency,
            'rounding_mode' => $mode,
            'rounding_precision' => $precision,
        ];
    }
}
