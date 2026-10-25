<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentAllocations\Pages\ListPaymentAllocations;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentAllocationUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Gate::before(static fn (): bool => true);
    }

    public function test_transaction_list_exposes_payment_allocation_action(): void
    {
        $this->actingAs(User::factory()->create());
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $customerInvoice = CustomerInvoice::factory()->create(['month' => '2026-01-01', 'currency_code' => 'RON']);
        $vendorInvoice = VendorInvoice::factory()->create(['month' => '2026-01-01', 'currency_code' => 'RON']);
        $tax = Tax::factory()->create(['month' => '2026-01-01']);
        $transaction = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $customerInvoice->id,
            'vendor_invoice_id' => $vendorInvoice->id,
            'tax_id' => $tax->id,
            'amount' => 125,
            'amount_decimal' => '125.00',
            'currency_code' => 'RON',
            'transaction_type_code' => 'money_in',
        ]);

        Livewire::test(ListTransactions::class)
            ->assertTableActionExists('proposePaymentAllocation', null, $transaction);
    }

    public function test_finance_allocation_screen_exposes_proposal_action(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListPaymentAllocations::class)
            ->assertTableHeaderActionsExistInOrder(['proposeAllocation']);
    }
}
