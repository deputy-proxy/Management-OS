<?php

namespace Tests\Feature;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Tests\TestCase;

class RomanianInvoicingRequirementFixturesTest extends TestCase
{
    public function test_fixture_covers_required_review_scenarios(): void
    {
        $scenarios = $this->fixtures()['scenarios'];

        foreach ([
            'standard_rate_arithmetic',
            'reduced_rate_arithmetic',
            'partial_payment',
            'split_payment',
            'partial_correction',
            'unmatched_payment',
            'duplicate_submission',
            'invalid_xml',
            'currency_mismatch',
        ] as $requiredScenario) {
            $this->assertArrayHasKey($requiredScenario, $scenarios);
        }
    }

    public function test_vat_and_gross_totals_reconcile_using_decimal_arithmetic(): void
    {
        $scenarios = $this->fixtures()['scenarios'];

        foreach (['standard_rate_arithmetic', 'reduced_rate_arithmetic'] as $key) {
            $scenario = $scenarios[$key];
            $calculatedVat = BigDecimal::of($scenario['net'])
                ->multipliedBy(BigDecimal::of($scenario['rate_percent']))
                ->dividedBy('100', 2, RoundingMode::HalfUp);
            $calculatedGross = BigDecimal::of($scenario['net'])->plus(BigDecimal::of($scenario['vat']));

            $this->assertSame($this->money($scenario['vat']), $this->money((string) $calculatedVat), $key.' VAT');
            $this->assertSame($this->money($scenario['gross']), $this->money((string) $calculatedGross), $key.' gross');
        }
    }

    public function test_partial_split_and_correction_totals_reconcile_independently(): void
    {
        $scenarios = $this->fixtures()['scenarios'];

        $partial = $scenarios['partial_payment'];
        $partialOutstanding = BigDecimal::of($partial['invoice_gross'])
            ->minus(BigDecimal::of($partial['confirmed_payment']))
            ->minus(BigDecimal::of($partial['fees']))
            ->minus(BigDecimal::of($partial['other_allocations']));
        $this->assertSame($this->money($partial['outstanding_by_arithmetic']), $this->money((string) $partialOutstanding));

        $split = $scenarios['split_payment'];
        $invoiceAllocations = array_reduce(
            $split['invoice_allocations'],
            fn (BigDecimal $total, string $amount): BigDecimal => $total->plus(BigDecimal::of($amount)),
            BigDecimal::zero(),
        );
        $allocatedTotal = $invoiceAllocations->plus(BigDecimal::of($split['fee_carve_out']));
        $unallocated = BigDecimal::of($split['payment'])->minus($allocatedTotal);
        $this->assertSame($this->money($split['allocated_total']), $this->money((string) $allocatedTotal));
        $this->assertSame($this->money($split['unallocated']), $this->money((string) $unallocated));

        $correction = $scenarios['partial_correction'];
        $creditVat = BigDecimal::of($correction['credit_net'])
            ->multipliedBy(BigDecimal::of($correction['assumed_rate_percent']))
            ->dividedBy('100', 2, RoundingMode::HalfUp);
        $creditGross = BigDecimal::of($correction['credit_net'])->plus($creditVat);
        $residual = BigDecimal::of($correction['original_gross'])->minus($creditGross);
        $this->assertSame($this->money($correction['credit_vat']), $this->money((string) $creditVat));
        $this->assertSame($this->money($correction['credit_gross']), $this->money((string) $creditGross));
        $this->assertSame($this->money($correction['residual_gross_by_arithmetic']), $this->money((string) $residual));
    }

    public function test_unmatched_duplicate_invalid_and_currency_edge_cases_are_explicit(): void
    {
        $scenarios = $this->fixtures()['scenarios'];

        $unmatched = $scenarios['unmatched_payment'];
        $this->assertSame($this->money($unmatched['payment']), $this->money($unmatched['unallocated']));
        $this->assertSame('unmatched', $unmatched['expected_state']);

        $duplicate = $scenarios['duplicate_submission'];
        $this->assertSame(2, $duplicate['attempts']);
        $this->assertSame(1, $duplicate['expected_invoice_records']);
        $this->assertSame(64, strlen($duplicate['payload_sha256']));

        $invalid = $scenarios['invalid_xml'];
        $this->assertNotEmpty($invalid['missing_required_fields']);
        $this->assertSame('rejected', $invalid['expected_state']);

        $currencyMismatch = $scenarios['currency_mismatch'];
        $this->assertNotSame($currencyMismatch['invoice_currency'], $currencyMismatch['payment_currency']);
        $this->assertNull($currencyMismatch['approved_fx_rule']);
        $this->assertSame('blocked_for_review', $currencyMismatch['expected_state']);
    }

    /** @return array{metadata: array<string, mixed>, scenarios: array<string, array<string, mixed>>} */
    private function fixtures(): array
    {
        $content = file_get_contents(base_path('tests/Fixtures/Finance/romanian-invoicing-requirements.json'));

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    private function money(string $amount): string
    {
        return (string) BigDecimal::of($amount)->toScale(2);
    }
}
