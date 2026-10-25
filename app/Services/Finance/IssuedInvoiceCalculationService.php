<?php

namespace App\Services\Finance;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Deterministic calculation for an issued-invoice draft.
 *
 * Tax categories, rates, currency support and rounding are supplied by an explicit,
 * versioned policy. This service intentionally contains no Romanian tax-rate defaults.
 */
class IssuedInvoiceCalculationService
{
    /**
     * Calculate line amounts, tax breakdowns and document totals.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $policy
     * @return array{
     *   policy_version: string,
     *   currency_code: string,
     *   lines: list<array<string, mixed>>,
     *   tax_breakdowns: list<array<string, mixed>>,
     *   totals: array{net_total: string, tax_total: string, gross_total: string}
     * }
     *
     * @throws ValidationException When the invoice or its explicit calculation policy is invalid.
     */
    public function calculate(array $invoice, array $policy): array
    {
        $errors = [];
        $roundingMode = $this->roundingMode($policy['rounding_mode'] ?? null, $errors);
        $policyVersion = is_string($policy['version'] ?? null) ? trim($policy['version']) : null;
        $allowedCurrencies = $policy['allowed_currencies'] ?? null;
        $taxCategories = $this->taxCategories($policy['tax_categories'] ?? null, $errors);

        if (! is_string($policyVersion) || trim($policyVersion) === '') {
            $errors['policy.version'][] = 'A versioned calculation policy is required.';
        }

        if (! is_array($allowedCurrencies) || $allowedCurrencies === []) {
            $errors['policy.allowed_currencies'][] = 'An explicit non-empty currency allowlist is required.';
        } elseif (! array_is_list($allowedCurrencies) || collect($allowedCurrencies)->contains(
            fn (mixed $currency): bool => ! is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1
        )) {
            $errors['policy.allowed_currencies'][] = 'Allowed currencies must be uppercase three-letter codes.';
        }

        if (! is_bool($policy['buyer_tax_identifier_required'] ?? null)) {
            $errors['policy.buyer_tax_identifier_required'][] = 'The buyer tax-identifier requirement must be explicit for the invoice profile.';
        }

        $currency = $invoice['currency_code'] ?? null;
        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            $errors['currency_code'][] = 'Currency must be an uppercase three-letter code.';
        } elseif (is_array($allowedCurrencies) && ! in_array($currency, $allowedCurrencies, true)) {
            $errors['currency_code'][] = 'Currency is not allowed by the selected calculation policy.';
        }

        $this->validateParties($invoice, (bool) ($policy['buyer_tax_identifier_required'] ?? false), $errors);

        $lines = $invoice['lines'] ?? null;
        if (! is_array($lines) || $lines === [] || ! array_is_list($lines)) {
            $errors['lines'][] = 'At least one invoice line is required.';
        } else {
            $lineNumbers = [];
            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    $errors["lines.{$index}"][] = 'Invoice line must be an object.';

                    continue;
                }

                $number = $line['line_number'] ?? null;
                if (! is_int($number) || $number < 1) {
                    $errors["lines.{$index}.line_number"][] = 'Line number must be a positive integer.';
                } elseif (in_array($number, $lineNumbers, true)) {
                    $errors["lines.{$index}.line_number"][] = 'Line numbers must be unique.';
                } else {
                    $lineNumbers[] = $number;
                }

                if (array_key_exists('currency_code', $line) && $line['currency_code'] !== $currency) {
                    $errors["lines.{$index}.currency_code"][] = 'Line currency must match the invoice currency; mixed-currency lines are not supported.';
                }

                foreach (['description', 'unit_code'] as $field) {
                    if (! is_string($line[$field] ?? null) || trim($line[$field]) === '') {
                        $errors["lines.{$index}.{$field}"][] = 'This field is required.';
                    }
                }

                foreach ([
                    'quantity' => 6,
                    'unit_price_net' => 6,
                    'discount_amount' => 2,
                ] as $field => $scale) {
                    $value = $this->decimal($line[$field] ?? null, $scale, "lines.{$index}.{$field}", $errors);
                    if ($value !== null && $value->compareTo(BigDecimal::zero()) < 0) {
                        $errors["lines.{$index}.{$field}"][] = 'Value cannot be negative.';
                    }
                    if ($field === 'quantity' && $value !== null && $value->compareTo(BigDecimal::zero()) === 0) {
                        $errors["lines.{$index}.{$field}"][] = 'Quantity must be greater than zero.';
                    }
                }

                $taxes = $line['taxes'] ?? null;
                if (! is_array($taxes) || $taxes === [] || ! array_is_list($taxes)) {
                    $errors["lines.{$index}.taxes"][] = 'At least one explicit tax category is required.';

                    continue;
                }

                foreach ($taxes as $taxIndex => $tax) {
                    if (! is_array($tax)) {
                        $errors["lines.{$index}.taxes.{$taxIndex}"][] = 'Tax entry must be an object.';

                        continue;
                    }

                    $scheme = $tax['tax_scheme_code'] ?? null;
                    $category = $tax['tax_category_code'] ?? null;
                    if (! is_string($scheme) || trim($scheme) === '') {
                        $errors["lines.{$index}.taxes.{$taxIndex}.tax_scheme_code"][] = 'Tax scheme is required.';
                    }
                    if (! is_string($category) || trim($category) === '') {
                        $errors["lines.{$index}.taxes.{$taxIndex}.tax_category_code"][] = 'Tax category is required.';
                    }
                    if (is_string($scheme) && is_string($category)) {
                        $key = $scheme.'|'.$category;
                        if (! isset($taxCategories[$key])) {
                            $errors["lines.{$index}.taxes.{$taxIndex}.tax_category_code"][] = 'Tax category is not present in the selected versioned policy.';

                            continue;
                        }

                        $rule = $taxCategories[$key];
                        $this->validateTaxAgainstRule($tax, $rule, $index, $taxIndex, $roundingMode, $errors);
                    }
                }
            }
        }

        if ($roundingMode === null) {
            $errors['policy.rounding_mode'][] = 'A supported explicit rounding mode is required.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /** @var RoundingMode $roundingMode */
        $calculatedLines = [];
        $taxTotals = [];
        $netTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();

        foreach ($lines as $lineIndex => $line) {
            $quantity = BigDecimal::of((string) $line['quantity']);
            $unitPrice = BigDecimal::of((string) $line['unit_price_net']);
            $discount = BigDecimal::of((string) $line['discount_amount'])->toScale(2, $roundingMode);
            $extended = $quantity->multipliedBy($unitPrice)->toScale(2, $roundingMode);
            $this->decimal($this->formatDecimal($extended, 2), 2, "lines.{$lineIndex}.calculated_extension", $errors);

            if ($discount->compareTo($extended) > 0) {
                $errors["lines.{$lineIndex}.discount_amount"][] = 'Discount cannot exceed the rounded line extension.';

                continue;
            }

            $lineNet = $extended->minus($discount)->toScale(2, $roundingMode);
            $this->decimal($this->formatDecimal($lineNet, 2), 2, "lines.{$lineIndex}.calculated_net_amount", $errors);
            if (array_key_exists('net_amount', $line)) {
                $this->assertDeclaredAmount($line['net_amount'], $this->formatDecimal($lineNet, 2), "lines.{$lineIndex}.net_amount", $errors);
            }
            $netTotal = $netTotal->plus($lineNet);

            $calculatedTaxes = [];
            foreach ($line['taxes'] as $taxIndex => $tax) {
                $rule = $taxCategories[$tax['tax_scheme_code'].'|'.$tax['tax_category_code']];
                $rate = $rule['rate_percent'];
                $taxableBase = $lineNet->toScale(2, $roundingMode);
                $taxAmount = $rate === null
                    ? BigDecimal::zero()->toScale(2, $roundingMode)
                    : $taxableBase->multipliedBy(BigDecimal::of($rate))->dividedBy('100', 2, $roundingMode);
                $this->decimal($this->formatDecimal($taxAmount, 2), 2, "lines.{$lineIndex}.taxes.{$taxIndex}.calculated_tax_amount", $errors);

                if ($rate === null && $taxAmount->compareTo(BigDecimal::zero()) !== 0) {
                    $errors["lines.{$lineIndex}.taxes.{$taxIndex}.tax_amount"][] = 'A tax category without a rate must calculate a zero tax amount.';
                }

                $calculatedTax = [
                    'tax_scheme_code' => $rule['tax_scheme_code'],
                    'tax_category_code' => $rule['tax_category_code'],
                    'tax_rate_percent' => $rate === null ? null : $this->formatDecimal(BigDecimal::of($rate), 4),
                    'taxable_base' => $this->formatDecimal($taxableBase, 2),
                    'tax_amount' => $this->formatDecimal($taxAmount, 2),
                    'exemption_reason_code' => $tax['exemption_reason_code'] ?? null,
                    'exemption_reason' => $tax['exemption_reason'] ?? null,
                    'reverse_charge' => (bool) ($tax['reverse_charge'] ?? false),
                ];
                if (array_key_exists('taxable_base', $tax)) {
                    $this->assertDeclaredAmount($tax['taxable_base'], $this->formatDecimal($taxableBase, 2), "lines.{$lineIndex}.taxes.{$taxIndex}.taxable_base", $errors);
                }
                if (array_key_exists('tax_amount', $tax)) {
                    $this->assertDeclaredAmount($tax['tax_amount'], $this->formatDecimal($taxAmount, 2), "lines.{$lineIndex}.taxes.{$taxIndex}.tax_amount", $errors);
                }
                $calculatedTaxes[] = $calculatedTax;
                $taxTotal = $taxTotal->plus($taxAmount);

                $breakdownKey = implode('|', [
                    $rule['tax_scheme_code'],
                    $rule['tax_category_code'],
                    $rate === null ? 'none' : $this->formatDecimal(BigDecimal::of($rate), 4),
                    (string) ($tax['exemption_reason_code'] ?? ''),
                    (string) ($tax['exemption_reason'] ?? ''),
                    (string) ($tax['reverse_charge'] ?? false),
                ]);

                if (! isset($taxTotals[$breakdownKey])) {
                    $taxTotals[$breakdownKey] = [
                        'tax_scheme_code' => $rule['tax_scheme_code'],
                        'tax_category_code' => $rule['tax_category_code'],
                        'tax_rate_percent' => $calculatedTax['tax_rate_percent'],
                        'taxable_base' => BigDecimal::zero(),
                        'tax_amount' => BigDecimal::zero(),
                        'exemption_reason_code' => $calculatedTax['exemption_reason_code'],
                        'exemption_reason' => $calculatedTax['exemption_reason'],
                        'reverse_charge' => $calculatedTax['reverse_charge'],
                    ];
                }
                $taxTotals[$breakdownKey]['taxable_base'] = $taxTotals[$breakdownKey]['taxable_base']->plus($taxableBase);
                $taxTotals[$breakdownKey]['tax_amount'] = $taxTotals[$breakdownKey]['tax_amount']->plus($taxAmount);
            }

            $calculatedLines[] = [
                'line_number' => $line['line_number'],
                'description' => trim($line['description']),
                'quantity' => $this->formatDecimal($quantity, 6),
                'unit_code' => trim($line['unit_code']),
                'unit_price_net' => $this->formatDecimal($unitPrice, 6),
                'discount_amount' => $this->formatDecimal($discount, 2),
                'net_amount' => $this->formatDecimal($lineNet, 2),
                'taxes' => $calculatedTaxes,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $taxBreakdowns = [];
        foreach ($taxTotals as $breakdown) {
            $taxBreakdowns[] = [
                ...$breakdown,
                'taxable_base' => $this->formatDecimal($breakdown['taxable_base']->toScale(2, $roundingMode), 2),
                'tax_amount' => $this->formatDecimal($breakdown['tax_amount']->toScale(2, $roundingMode), 2),
            ];
        }

        $netTotal = $netTotal->toScale(2, $roundingMode);
        $taxTotal = $taxTotal->toScale(2, $roundingMode);
        $grossTotal = $netTotal->plus($taxTotal)->toScale(2, $roundingMode);
        $totals = [
            'net_total' => $this->formatDecimal($netTotal, 2),
            'tax_total' => $this->formatDecimal($taxTotal, 2),
            'gross_total' => $this->formatDecimal($grossTotal, 2),
        ];

        $this->assertDeclaredAmount($invoice['totals']['net_total'] ?? null, $totals['net_total'], 'totals.net_total', $errors);
        $this->assertDeclaredAmount($invoice['totals']['tax_total'] ?? null, $totals['tax_total'], 'totals.tax_total', $errors);
        $this->assertDeclaredAmount($invoice['totals']['gross_total'] ?? null, $totals['gross_total'], 'totals.gross_total', $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'policy_version' => $policyVersion,
            'currency_code' => $currency,
            'lines' => $calculatedLines,
            'tax_breakdowns' => $taxBreakdowns,
            'totals' => $totals,
        ];
    }

    /**
     * @param  array<string, mixed>  $tax
     * @param  array<string, mixed>  $rule
     * @param  array<string, list<string>>  $errors
     */
    private function validateTaxAgainstRule(
        array $tax,
        array $rule,
        int $lineIndex,
        int $taxIndex,
        ?RoundingMode $roundingMode,
        array &$errors,
    ): void {
        $prefix = "lines.{$lineIndex}.taxes.{$taxIndex}";
        $rate = $rule['rate_percent'];
        $suppliedRate = $tax['tax_rate_percent'] ?? null;

        if ($rate === null) {
            if ($suppliedRate !== null && $suppliedRate !== '') {
                $errors["{$prefix}.tax_rate_percent"][] = 'This category does not permit a numeric tax rate under the selected policy.';
            }
            if (($rule['requires_exemption_reason'] ?? false)
                && blank($tax['exemption_reason_code'] ?? null)
                && blank($tax['exemption_reason'] ?? null)) {
                $errors["{$prefix}.exemption_reason"][] = 'An exemption reason/code is required by the selected tax policy.';
            }
        } else {
            $supplied = $this->decimal($suppliedRate, 4, "{$prefix}.tax_rate_percent", $errors, 7);
            if ($supplied !== null && $supplied->compareTo(BigDecimal::of((string) $rate)) !== 0) {
                $errors["{$prefix}.tax_rate_percent"][] = 'Tax rate disagrees with the selected versioned tax policy.';
            }
        }

        if (array_key_exists('reverse_charge', $tax) && ! is_bool($tax['reverse_charge'])) {
            $errors["{$prefix}.reverse_charge"][] = 'Reverse-charge indicator must be a boolean.';
        }

        if (($tax['reverse_charge'] ?? false) && ! ($rule['allows_reverse_charge'] ?? false)) {
            $errors["{$prefix}.reverse_charge"][] = 'Reverse charge is not allowed by the selected tax policy.';
        }

        if (isset($tax['taxable_base'])) {
            $this->decimal($tax['taxable_base'], 2, "{$prefix}.taxable_base", $errors);
        }
        if (isset($tax['tax_amount'])) {
            $this->decimal($tax['tax_amount'], 2, "{$prefix}.tax_amount", $errors);
        }

        if ($roundingMode === null) {
            return;
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @param  array<string, list<string>>  $errors
     */
    private function validateParties(array $invoice, bool $buyerTaxIdentifierRequired, array &$errors): void
    {
        $seller = $invoice['parties']['seller'] ?? null;
        $buyer = $invoice['parties']['buyer'] ?? null;

        foreach (['seller' => $seller, 'buyer' => $buyer] as $role => $party) {
            if (! is_array($party)) {
                $errors["parties.{$role}"][] = 'Party snapshot is required.';

                continue;
            }

            foreach (['legal_name', 'country_code'] as $field) {
                if (! is_string($party[$field] ?? null) || trim($party[$field]) === '') {
                    $errors["parties.{$role}.{$field}"][] = 'This field is required.';
                }
            }

            if (is_string($party['country_code'] ?? null)
                && preg_match('/^[A-Z]{2}$/D', $party['country_code']) !== 1) {
                $errors["parties.{$role}.country_code"][] = 'Country code must be uppercase ISO-style alpha-2.';
            }
        }

        if (is_array($seller)) {
            foreach (['tax_identifier_type', 'tax_identifier'] as $field) {
                if (! is_string($seller[$field] ?? null) || trim($seller[$field]) === '') {
                    $errors["parties.seller.{$field}"][] = 'Issuer fiscal identifier and identifier type are required.';
                }
            }
        }

        if (is_array($buyer)) {
            $hasBuyerIdentifier = filled($buyer['tax_identifier'] ?? null)
                || filled($buyer['vat_registration_id'] ?? null)
                || filled($buyer['registration_id'] ?? null);

            if ($buyerTaxIdentifierRequired && ! $hasBuyerIdentifier) {
                $errors['parties.buyer.tax_identifier'][] = 'The selected invoice profile requires a buyer fiscal identifier.';
            }

            if (filled($buyer['tax_identifier'] ?? null) && blank($buyer['tax_identifier_type'] ?? null)) {
                $errors['parties.buyer.tax_identifier_type'][] = 'Buyer tax identifier type is required when a tax identifier is supplied.';
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function decimal(mixed $raw, int $scale, string $field, array &$errors, int $precision = 19): ?BigDecimal
    {
        if (! is_string($raw) && ! is_int($raw)) {
            $errors[$field][] = 'A decimal string or integer is required; floating-point values are not accepted.';

            return null;
        }

        $value = (string) $raw;
        if (preg_match('/^-?\d+(?:\.\d+)?$/D', $value) !== 1) {
            $errors[$field][] = 'Value must be a plain decimal without separators or exponent notation.';

            return null;
        }

        $parts = explode('.', ltrim($value, '-'), 2);
        $integerDigits = strlen(ltrim($parts[0], '0'));
        $fractionDigits = strlen($parts[1] ?? '');

        if ($fractionDigits > $scale) {
            $errors[$field][] = "Value cannot have more than {$scale} decimal places.";

            return null;
        }

        $maxIntegerDigits = $precision - $scale;
        if ($integerDigits > $maxIntegerDigits) {
            $errors[$field][] = 'Value exceeds the supported decimal precision.';

            return null;
        }

        try {
            return BigDecimal::of($value);
        } catch (Throwable) {
            $errors[$field][] = 'Value is not a valid decimal.';

            return null;
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function assertDeclaredAmount(mixed $raw, string $expected, string $field, array &$errors): void
    {
        $declared = $this->decimal($raw, 2, $field, $errors);
        if ($declared !== null && $declared->compareTo(BigDecimal::of($expected)) !== 0) {
            $errors[$field][] = "Declared total does not match calculated total {$expected}.";
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function roundingMode(mixed $raw, array &$errors): ?RoundingMode
    {
        if (! is_string($raw)) {
            $errors['policy.rounding_mode'][] = 'Rounding mode must be explicit.';

            return null;
        }

        return match (strtoupper($raw)) {
            'HALF_UP' => RoundingMode::HalfUp,
            'HALF_EVEN' => RoundingMode::HalfEven,
            'HALF_DOWN' => RoundingMode::HalfDown,
            'UP' => RoundingMode::Up,
            'DOWN' => RoundingMode::Down,
            'CEILING' => RoundingMode::Ceiling,
            'FLOOR' => RoundingMode::Floor,
            default => $this->invalidRoundingMode($errors),
        };
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function invalidRoundingMode(array &$errors): null
    {
        $errors['policy.rounding_mode'][] = 'Unsupported rounding mode.';

        return null;
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @return array<string, array<string, mixed>>
     */
    private function taxCategories(mixed $raw, array &$errors): array
    {
        if (! is_array($raw) || $raw === [] || ! array_is_list($raw)) {
            $errors['policy.tax_categories'][] = 'An explicit non-empty versioned tax-category list is required.';

            return [];
        }

        $categories = [];
        foreach ($raw as $index => $rule) {
            if (! is_array($rule)) {
                $errors["policy.tax_categories.{$index}"][] = 'Tax-category rule must be an object.';

                continue;
            }

            $scheme = $rule['tax_scheme_code'] ?? null;
            $category = $rule['tax_category_code'] ?? null;
            $rate = $rule['rate_percent'] ?? null;

            if (! array_key_exists('rate_percent', $rule)) {
                $errors["policy.tax_categories.{$index}.rate_percent"][] = 'Tax rate must be explicit; use null only for a policy-defined no-rate category.';
            }

            if (! is_string($scheme) || trim($scheme) === '' || ! is_string($category) || trim($category) === '') {
                $errors["policy.tax_categories.{$index}"][] = 'Tax scheme and category codes are required.';

                continue;
            }

            if ($rate !== null) {
                $parsedRate = $this->decimal($rate, 4, "policy.tax_categories.{$index}.rate_percent", $errors, 7);
                if ($parsedRate !== null && $parsedRate->compareTo(BigDecimal::zero()) < 0) {
                    $errors["policy.tax_categories.{$index}.rate_percent"][] = 'Tax rate cannot be negative.';
                }
            }

            $key = $scheme.'|'.$category;
            if (isset($categories[$key])) {
                $errors["policy.tax_categories.{$index}"][] = 'Duplicate tax scheme/category pair in policy.';

                continue;
            }

            foreach (['requires_exemption_reason', 'allows_reverse_charge'] as $booleanField) {
                if (! is_bool($rule[$booleanField] ?? null)) {
                    $errors["policy.tax_categories.{$index}.{$booleanField}"][] = 'Policy flag must be an explicit boolean.';
                }
            }

            $categories[$key] = [
                'tax_scheme_code' => $scheme,
                'tax_category_code' => $category,
                'rate_percent' => $rate === null ? null : (string) $rate,
                'requires_exemption_reason' => (bool) ($rule['requires_exemption_reason'] ?? false),
                'allows_reverse_charge' => (bool) ($rule['allows_reverse_charge'] ?? false),
            ];
        }

        return $categories;
    }

    private function formatDecimal(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale);
    }
}
