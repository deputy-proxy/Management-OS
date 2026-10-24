<?php

namespace Tests\Feature;

use App\Filament\Resources\BankStatementImportBatches\Pages\ListBankStatementImportBatches;
use App\Models\FinancialAccount;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BankStatementImportPreviewUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::before(static fn (): bool => true);
    }

    public function test_upload_is_not_persisted_as_a_batch_without_explicit_confirmation(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $fixture = base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.pdf');
        $upload = UploadedFile::fake()->createWithContent('statement.pdf', file_get_contents($fixture));

        Livewire::test(ListBankStatementImportBatches::class)
            ->mountAction('previewStatement')
            ->fillForm([
                'financial_account_id' => $account->id,
                'bank_code' => 'ing',
                'statement_file' => $upload,
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['confirm_persistence']);

        $this->assertDatabaseCount('bank_statement_import_batches', 0);
        $this->assertDatabaseCount('bank_statement_import_items', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_explicit_confirmation_persists_the_same_server_validated_preview(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $fixture = base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.pdf');
        $upload = UploadedFile::fake()->createWithContent('statement.pdf', file_get_contents($fixture));

        Livewire::test(ListBankStatementImportBatches::class)
            ->mountAction('previewStatement')
            ->fillForm([
                'financial_account_id' => $account->id,
                'bank_code' => 'ing',
                'confirm_persistence' => true,
            ])
            ->set('mountedActions.0.data.statement_file', $upload)
            ->assertSet('mountedActions.0.data.preview_summary', static fn ($value): bool => is_string($value) && str_contains($value, 'Parsed rows:') && str_contains($value, 'Opening balance:') && str_contains($value, 'ROWS (date') && str_contains($value, 'No import batch is saved'))
            ->callMountedAction();

        $this->assertDatabaseCount('bank_statement_import_batches', 1);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_preview_action_is_available_to_authorized_users_and_requires_explicit_persistence_confirmation(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListBankStatementImportBatches::class)
            ->assertActionExists('previewStatement', function ($action): bool {
                $names = collect($action->getSchema(new Schema)->getComponents())
                    ->map(static fn ($component): ?string => $component->getName())
                    ->all();

                return in_array('preview_summary', $names, true)
                    && in_array('confirm_persistence', $names, true);
            });
    }
}
