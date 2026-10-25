<?php

namespace Tests\Unit;

use App\Services\Finance\IssuedInvoiceCalculationService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IssuedInvoiceCalculationServiceTest extends TestCase
{
    public function test_calculates_discounted_line_tax_and_document_totals_with_decimal_arithmetic(): void
    {
        $result = app(IssuedInvoiceCalculationService::class)->calculate(
            $this->invoice(),
            $this->policy(),
        );

        $this->assertSame('policy-test-v1', $result['policy_version']);
        $this->assertSame('RON', $result['currency_code']);
        $this->assertSame('90.00', $result['lines'][0]['net_amount']);
        $this->assertSame('90.00', $result['lines'][0]['taxes'][0]['taxable_base']);
        $this->assertSame('18.90', $result['lines'][0]['taxes'][0]['tax_amount']);
        $this->assertSame('90.00', $result['tax_breakdowns'][0]['taxable_base']);
        $this->assertSame('18.90', $result['tax_breakdowns'][0]['tax_amount']);
        $this->assertSame([
            'net_total' => '90.00',
            'tax_total' => '18.90',
            'gross_total' => '108.90',
        ], $result['totals']);
    }

    public function test_reconciles_service_results_to_independent_synthetic_tax_fixtures(): void
    {
        $content = file_get_contents(base_path('tests/Fixtures/Finance/romanian-invoicing-requirements.json'));
        $fixtures = json_decode($content, true, flags: JSON_THROW_ON_ERROR)['scenarios'];

        foreach (['standard_rate_arithmetic', 'reduced_rate_arithmetic'] as $scenarioKey) {
            $fixture = $fixtures[$scenarioKey];
            $invoice = $this->invoice();
            $invoice['lines'][0]['unit_price_net'] = $fixture['net'];
            $invoice['lines'][0]['discount_amount'] = '0.00';
            $invoice['lines'][0]['net_amount'] = $fixture['net'];
            $invoice['lines'][0]['taxes'][0]['tax_category_code'] = $scenarioKey;
            $invoice['lines'][0]['taxes'][0]['tax_rate_percent'] = $fixture['rate_percent'];
            $invoice['lines'][0]['taxes'][0]['taxable_base'] = $fixture['net'];
            $invoice['lines'][0]['taxes'][0]['tax_amount'] = $fixture['vat'];
            $invoice['totals'] = [
                'net_total' => $fixture['net'],
                'tax_total' => $fixture['vat'],
                'gross_total' => $fixture['gross'],
            ];

            $policy = $this->policy();
            $policy['tax_categories'] = [[
                'tax_scheme_code' => 'VAT',
                'tax_category_code' => $scenarioKey,
                'rate_percent' => $fixture['rate_percent'],
                'requires_exemption_reason' => false,
                'allows_reverse_charge' => false,
            ]];

            $result = app(IssuedInvoiceCalculationService::class)->calculate($invoice, $policy);
            $this->assertSame($fixture['net'], $result['totals']['net_total'], $scenarioKey.' net');
            $this->assertSame($fixture['vat'], $result['totals']['tax_total'], $scenarioKey.' VAT');
            $this->assertSame($fixture['gross'], $result['totals']['gross_total'], $scenarioKey.' gross');
        }
    }

    public function test_uses_explicit_half_up_rounding_for_line_extension_and_tax(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][0]['quantity'] = '1.000000';
        $invoice['lines'][0]['unit_price_net'] = '1.005000';
        $invoice['lines'][0]['discount_amount'] = '0.00';
        $invoice['lines'][0]['net_amount'] = '1.01';
        $invoice['lines'][0]['taxes'][0]['taxable_base'] = '1.01';
        $invoice['lines'][0]['taxes'][0]['tax_amount'] = '0.21';
        $invoice['totals'] = ['net_total' => '1.01', 'tax_total' => '0.21', 'gross_total' => '1.22'];

        $result = app(IssuedInvoiceCalculationService::class)->calculate($invoice, $this->policy());

        $this->assertSame('1.01', $result['lines'][0]['net_amount']);
        $this->assertSame('0.21', $result['lines'][0]['taxes'][0]['tax_amount']);
        $this->assertSame('1.22', $result['totals']['gross_total']);
    }

    public function test_aggregates_tax_breakdowns_by_scheme_category_rate_and_reason(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][] = [
            'line_number' => 2,
            'description' => 'Another service',
            'quantity' => '2.000000',
            'unit_code' => 'C62',
            'unit_price_net' => '5.000000',
            'discount_amount' => '0.00',
            'net_amount' => '10.00',
            'taxes' => [[
                'tax_scheme_code' => 'VAT',
                'tax_category_code' => 'STANDARD',
                'tax_rate_percent' => '21.0000',
                'taxable_base' => '10.00',
                'tax_amount' => '2.10',
            ]],
        ];
        $invoice['totals'] = ['net_total' => '100.00', 'tax_total' => '21.00', 'gross_total' => '121.00'];

        $result = app(IssuedInvoiceCalculationService::class)->calculate($invoice, $this->policy());

        $this->assertCount(1, $result['tax_breakdowns']);
        $this->assertSame('100.00', $result['tax_breakdowns'][0]['taxable_base']);
        $this->assertSame('21.00', $result['tax_breakdowns'][0]['tax_amount']);
        $this->assertSame('100.00', $result['totals']['net_total']);
        $this->assertSame('21.00', $result['totals']['tax_total']);
    }

    public function test_rejects_unapproved_tax_categories_instead_of_guessing_a_rate(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][0]['taxes'][0]['tax_category_code'] = 'UNKNOWN';

        $this->assertValidationError(
            $invoice,
            $this->policy(),
            'lines.0.taxes.0.tax_category_code',
        );
    }

    public function test_rejects_a_rate_that_disagrees_with_the_versioned_tax_policy(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][0]['taxes'][0]['tax_rate_percent'] = '11.0000';

        $this->assertValidationError(
            $invoice,
            $this->policy(),
            'lines.0.taxes.0.tax_rate_percent',
        );
    }

    public function test_rejects_inconsistent_line_tax_and_document_totals(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][0]['net_amount'] = '99.00';
        $invoice['lines'][0]['taxes'][0]['taxable_base'] = '99.00';
        $invoice['lines'][0]['taxes'][0]['tax_amount'] = '20.79';
        $invoice['totals'] = ['net_total' => '99.00', 'tax_total' => '20.79', 'gross_total' => '119.79'];

        try {
            app(IssuedInvoiceCalculationService::class)->calculate($invoice, $this->policy());
            $this->fail('Expected inconsistent invoice amounts to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.net_amount', $exception->errors());
            $this->assertArrayHasKey('lines.0.taxes.0.taxable_base', $exception->errors());
            $this->assertArrayHasKey('lines.0.taxes.0.tax_amount', $exception->errors());
        }
    }

    public function test_rejects_document_totals_that_do_not_match_calculated_amounts(): void
    {
        $invoice = $this->invoice();
        $invoice['totals'] = ['net_total' => '91.00', 'tax_total' => '19.00', 'gross_total' => '110.00'];

        try {
            app(IssuedInvoiceCalculationService::class)->calculate($invoice, $this->policy());
            $this->fail('Expected inconsistent document totals to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('totals.net_total', $exception->errors());
            $this->assertArrayHasKey('totals.tax_total', $exception->errors());
            $this->assertArrayHasKey('totals.gross_total', $exception->errors());
        }
    }

    public function test_requires_explicit_party_snapshots_and_profile_specific_buyer_identifier_policy(): void
    {
        $invoice = $this->invoice();
        $invoice['parties']['seller']['tax_identifier'] = '';
        $invoice['parties']['buyer']['tax_identifier'] = '';
        $invoice['parties']['buyer']['vat_registration_id'] = '';
        $invoice['parties']['buyer']['registration_id'] = '';

        try {
            app(IssuedInvoiceCalculationService::class)->calculate($invoice, $this->policy());
            $this->fail('Expected missing required fiscal identifiers to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parties.seller.tax_identifier', $exception->errors());
            $this->assertArrayHasKey('parties.buyer.tax_identifier', $exception->errors());
        }

        $policy = $this->policy();
        $policy['buyer_tax_identifier_required'] = false;
        $invoice['parties']['buyer']['tax_identifier_type'] = null;
        $invoice['parties']['seller']['tax_identifier'] = 'SYNTHETIC-SELLER';
        $result = app(IssuedInvoiceCalculationService::class)->calculate($invoice, $policy);

        $this->assertSame('108.90', $result['totals']['gross_total']);
    }

    public function test_rejects_floating_point_inputs_and_excess_precision(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][0]['quantity'] = 1.0;

        $this->assertValidationError($invoice, $this->policy(), 'lines.0.quantity');

        $invoice = $this->invoice();
        $invoice['lines'][0]['unit_price_net'] = '100.0000001';

        $this->assertValidationError($invoice, $this->policy(), 'lines.0.unit_price_net');
    }

    public function test_rejects_duplicate_line_numbers_negative_values_and_discounts_exceeding_extension(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][] = $invoice['lines'][0];
        $invoice['lines'][1]['description'] = 'Duplicate number';

        $this->assertValidationError($invoice, $this->policy(), 'lines.1.line_number');

        $invoice = $this->invoice();
        $invoice['lines'][0]['discount_amount'] = '101.00';

        $this->assertValidationError($invoice, $this->policy(), 'lines.0.discount_amount');

        $invoice = $this->invoice();
        $invoice['lines'][0]['quantity'] = '-1.000000';

        $this->assertValidationError($invoice, $this->policy(), 'lines.0.quantity');
    }

    public function test_requires_exemption_reason_for_a_policy_category_without_a_numeric_rate(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][0]['taxes'][0] = [
            'tax_scheme_code' => 'VAT',
            'tax_category_code' => 'EXEMPT',
            'tax_rate_percent' => null,
        ];
        $invoice['totals'] = ['net_total' => '90.00', 'tax_total' => '0.00', 'gross_total' => '90.00'];
        $policy = $this->policy();
        $policy['tax_categories'][] = [
            'tax_scheme_code' => 'VAT',
            'tax_category_code' => 'EXEMPT',
            'rate_percent' => null,
            'requires_exemption_reason' => true,
            'allows_reverse_charge' => false,
        ];

        $this->assertValidationError($invoice, $policy, 'lines.0.taxes.0.exemption_reason');
        $invoice['lines'][0]['taxes'][0]['exemption_reason_code'] = 'TEST-EXEMPTION';
        $result = app(IssuedInvoiceCalculationService::class)->calculate($invoice, $policy);
        $this->assertSame('0.00', $result['totals']['tax_total']);
        $this->assertSame('90.00', $result['totals']['gross_total']);
    }

    public function test_rejects_line_currency_that_differs_from_invoice_currency(): void
    {
        $invoice = $this->invoice();
        $invoice['lines'][0]['currency_code'] = 'EUR';

        $this->assertValidationError($invoice, $this->policy(), 'lines.0.currency_code');

        $invoice = $this->invoice();
        $invoice['currency_code'] = 'USD';
        $this->assertValidationError($invoice, $this->policy(), 'currency_code');
    }

    public function test_rejects_missing_or_ambiguous_calculation_policy(): void
    {
        $invoice = $this->invoice();

        $this->assertValidationError($invoice, [], 'policy.version');

        $policy = $this->policy();
        $policy['allowed_currencies'] = ['RON', 'not-a-currency'];
        $this->assertValidationError($invoice, $policy, 'policy.allowed_currencies');

        $policy = $this->policy();
        $policy['rounding_mode'] = 'MAGIC';
        $this->assertValidationError($invoice, $policy, 'policy.rounding_mode');

        $policy = $this->policy();
        unset($policy['tax_categories'][0]['rate_percent']);
        $this->assertValidationError($invoice, $policy, 'policy.tax_categories.0.rate_percent');

        $policy = $this->policy();
        $policy['tax_categories'][0]['rate_percent'] = '1234.0000';
        $this->assertValidationError($invoice, $policy, 'policy.tax_categories.0.rate_percent');
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $policy
     */
    private function assertValidationError(array $invoice, array $policy, string $key): void
    {
        try {
            app(IssuedInvoiceCalculationService::class)->calculate($invoice, $policy);
            $this->fail("Expected validation error for {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors(), $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(): array
    {
        return [
            'currency_code' => 'RON',
            'parties' => [
                'seller' => [
                    'legal_name' => 'Synthetic Seller SRL',
                    'country_code' => 'RO',
                    'tax_identifier_type' => 'CUI',
                    'tax_identifier' => 'SYNTHETIC-SELLER',
                ],
                'buyer' => [
                    'legal_name' => 'Synthetic Buyer SRL',
                    'country_code' => 'RO',
                    'tax_identifier_type' => 'CUI',
                    'tax_identifier' => 'SYNTHETIC-BUYER',
                ],
            ],
            'lines' => [[
                'line_number' => 1,
                'description' => 'Synthetic service',
                'quantity' => '1.000000',
                'unit_code' => 'C62',
                'unit_price_net' => '100.000000',
                'discount_amount' => '10.00',
                'net_amount' => '90.00',
                'taxes' => [[
                    'tax_scheme_code' => 'VAT',
                    'tax_category_code' => 'STANDARD',
                    'tax_rate_percent' => '21.0000',
                    'taxable_base' => '90.00',
                    'tax_amount' => '18.90',
                ]],
            ]],
            'totals' => [
                'net_total' => '90.00',
                'tax_total' => '18.90',
                'gross_total' => '108.90',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function policy(): array
    {
        return [
            'version' => 'policy-test-v1',
            'rounding_mode' => 'HALF_UP',
            'allowed_currencies' => ['RON', 'EUR'],
            'buyer_tax_identifier_required' => true,
            'tax_categories' => [[
                'tax_scheme_code' => 'VAT',
                'tax_category_code' => 'STANDARD',
                'rate_percent' => '21.0000',
                'requires_exemption_reason' => false,
                'allows_reverse_charge' => false,
            ]],
        ];
    }
}
