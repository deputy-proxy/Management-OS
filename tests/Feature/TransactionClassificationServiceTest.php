<?php

namespace Tests\Feature;

use App\Services\Finance\TransactionClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_classification_uses_explicit_direction_without_inventing_transaction_subtypes(): void
    {
        $classifier = app(TransactionClassificationService::class);

        $customerIn = $classifier->classify('Customer', 'money_in');
        $customerOut = $classifier->classify('Customer', 'money_out');
        $vendorOut = $classifier->classify('Vendor', 'money_out');
        $taxOut = $classifier->classify('Tax', 'money_out');
        $otherIn = $classifier->classify('Other', 'money_in');
        $legacy = $classifier->classify('Customer', null);
        $unknownType = $classifier->classify('Card settlement', 'money_in');

        $this->assertSame('customer_money_in', $customerIn['code']);
        $this->assertSame('customer_money_out', $customerOut['code']);
        $this->assertSame('vendor_money_out', $vendorOut['code']);
        $this->assertSame('tax_money_out', $taxOut['code']);
        $this->assertSame('other_money_in', $otherIn['code']);
        $this->assertSame('unclassified_legacy', $legacy['code']);
        $this->assertSame('unclassified', $unknownType['status']);
    }
}
