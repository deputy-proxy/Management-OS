<?php

namespace App\Filament\Resources\BankStatementImportBatches\Pages;

use App\Filament\Resources\BankStatementImportBatches\BankStatementImportBatchResource;
use App\Models\FinancialAccount;
use App\Services\BankStatements\BankStatementImportBatchService;
use App\Services\BankStatements\BankStatementParseException;
use App\Services\BankStatements\IngStatementPdfParser;
use App\Services\BankStatements\IngStatementTransactionNormalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListBankStatementImportBatches extends ListRecords
{
    protected static string $resource = BankStatementImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewStatement')
                ->label('Preview bank statement')
                ->icon('heroicon-o-document-arrow-up')
                ->visible(fn (): bool => hexa()->can('transaction.create'))
                ->schema([
                    Select::make('financial_account_id')
                        ->label('Financial account')
                        ->options(FinancialAccount::query()->orderBy('title')->pluck('title', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('bank_code')
                        ->options(['ing' => 'ING Romania'])
                        ->required()
                        ->default('ing'),
                    FileUpload::make('statement_file')
                        ->label('PDF statement')
                        ->disk('local')
                        ->directory('temporary-bank-statement-imports')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(25600)
                        ->required(),
                ])
                ->action(function (array $data, BankStatementImportBatchService $service, IngStatementPdfParser $parser, IngStatementTransactionNormalizer $normalizer): void {
                    $path = $data['statement_file'];
                    try {
                        $parsed = $parser->parse(Storage::disk('local')->path($path));
                        $normalized = $normalizer->normalize($parsed);
                        $batch = $service->createPreview(
                            $data['bank_code'],
                            FinancialAccount::query()->findOrFail($data['financial_account_id']),
                            auth()->user(),
                            $normalized,
                        );
                        Notification::make()
                            ->title('Statement preview created')
                            ->body('Import batch #'.$batch->id.' is ready for review. No transactions were posted.')
                            ->success()
                            ->send();
                    } catch (BankStatementParseException $exception) {
                        Notification::make()->title('Statement could not be parsed')->body('Validation code: '.$exception->errorCode)->danger()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Statement preview failed')->body('No transactions were posted. Check the selected account and statement, then retry if appropriate.')->danger()->send();
                    } finally {
                        Storage::disk('local')->delete($path);
                    }
                }),
        ];
    }
}
