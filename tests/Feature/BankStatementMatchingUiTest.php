<?php

namespace Tests\Feature;

use App\Filament\Resources\BankStatementImportItems\Pages\ListBankStatementImportItems;
use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportItem;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class BankStatementMatchingUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Gate::before(static fn (): bool => true);
    }

    public function test_completed_import_rows_expose_a_manual_review_action(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $batch = BankStatementImportBatch::query()->create([
            'financial_account_id' => $account->id,
            'created_by' => $actor->id,
            'bank_code' => 'test',
            'source_sha256' => hash('sha256', 'ui-batch'),
            'parser_version' => 'test-v1',
            'status' => BankStatementImportBatch::STATUS_COMPLETED,
            'currency_code' => 'RON',
            'statement_start' => '2026-01-01',
            'statement_end' => '2026-01-31',
            'transaction_count' => 1,
            'review_required_count' => 0,
            'warning_count' => 0,
            'preview_payload' => [],
            'completed_at' => now(),
        ]);
        $item = BankStatementImportItem::query()->create([
            'batch_id' => $batch->id,
            'financial_account_id' => $account->id,
            'line_fingerprint' => hash('sha256', 'ui-row'),
            'transaction_date' => '2026-01-15',
            'direction' => 'debit',
            'amount_decimal' => '125.00',
            'currency_code' => 'RON',
            'classification_status' => 'unclassified',
            'description' => 'Synthetic statement row',
            'source_sha256' => $batch->source_sha256,
            'parser_version' => $batch->parser_version,
            'source_page' => 1,
            'source_row' => 1,
            'review_reasons' => [],
        ]);

        Livewire::test(ListBankStatementImportItems::class)
            ->assertTableActionExists('recordMatch', null, $item);
    }
}
