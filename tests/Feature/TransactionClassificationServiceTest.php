<?php

namespace Tests\Feature;

use App\Services\Finance\TransactionClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_supported_type_and_direction_pairs_have_stable_classifications(): void
    {
        $classifier = app(TransactionClassificationService::class);
        $types = [
            'Customer' => 'customer',
            'Vendor' => 'vendor',
            'Tax' => 'tax',
            'Other' => 'other',
        ];

        foreach ($types as $label => $prefix) {
            foreach (['money_in', 'money_out'] as $direction) {
                $result = $classifier->classify($label, $direction);

                $this->assertSame($prefix.'_'.$direction, $result['code']);
                $this->assertSame('classified', $result['status']);
                $this->assertSame($direction, $result['direction']);
            }
        }
    }

    public function test_classification_normalizes_case_and_whitespace_without_guessing_subtypes(): void
    {
        $classifier = app(TransactionClassificationService::class);

        $this->assertSame('customer_money_in', $classifier->classify('  CUSTOMER ', ' MONEY_IN ')['code']);
        $this->assertSame('vendor_money_out', $classifier->classify('Vendor', 'money_out')['code']);
        $this->assertSame('unclassified_legacy', $classifier->classify('Card settlement', 'money_in')['code']);
        $this->assertSame('unclassified', $classifier->classify('Card settlement', 'money_in')['status']);
    }

    public function test_missing_or_unsupported_direction_stays_unclassified_and_never_infers_direction(): void
    {
        $classifier = app(TransactionClassificationService::class);

        foreach ([null, '', 'transfer', 'in', 'out'] as $direction) {
            $result = $classifier->classify('Customer', $direction);

            $this->assertSame('unclassified_legacy', $result['code']);
            $this->assertSame('unclassified', $result['status']);
            $this->assertNull($result['direction']);
        }
    }

    public function test_unsupported_type_with_a_valid_direction_keeps_direction_but_not_a_guessed_type(): void
    {
        $result = app(TransactionClassificationService::class)->classify('Card settlement', 'money_in');

        $this->assertSame('unclassified_legacy', $result['code']);
        $this->assertSame('unclassified', $result['status']);
        $this->assertSame('money_in', $result['direction']);
    }
}
