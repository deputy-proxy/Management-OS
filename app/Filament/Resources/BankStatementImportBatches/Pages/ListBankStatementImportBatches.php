<?php

namespace App\Filament\Resources\BankStatementImportBatches\Pages;

use App\Filament\Resources\BankStatementImportBatches\BankStatementImportBatchResource;
use App\Models\FinancialAccount;
use App\Services\BankStatements\BankStatementImportBatchService;
use App\Services\BankStatements\BankStatementParseException;
use App\Services\BankStatements\IngStatementPdfParser;
use App\Services\BankStatements\IngStatementTransactionNormalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set, IngStatementPdfParser $parser, IngStatementTransactionNormalizer $normalizer, BankStatementImportBatchService $service) => $this->refreshPreview($get, $set, $parser, $normalizer, $service)),
                    Select::make('bank_code')
                        ->options(['ing' => 'ING Romania'])
                        ->required()
                        ->default('ing')
                        ->live(),
                    FileUpload::make('statement_file')
                        ->label('PDF statement')
                        ->disk(config('livewire.temporary_file_upload.disk', 'local'))
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(25600)
                        ->storeFiles(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set, IngStatementPdfParser $parser, IngStatementTransactionNormalizer $normalizer, BankStatementImportBatchService $service) => $this->refreshPreview($get, $set, $parser, $normalizer, $service)),
                    Textarea::make('preview_summary')
                        ->label('Parsed candidates and validation findings')
                        ->rows(14)
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Choose an account and upload a statement to inspect the parsed rows before saving a batch.')
                        ->columnSpanFull(),
                    Checkbox::make('confirm_persistence')
                        ->label('I reviewed the rows and validation findings and confirm saving this batch for review.')
                        ->accepted()
                        ->required(),
                ])
                ->modalWidth('5xl')
                ->modalCancelActionLabel('Discard preview')
                ->action(function (array $data, BankStatementImportBatchService $service, IngStatementPdfParser $parser, IngStatementTransactionNormalizer $normalizer): void {
                    $uploadedFile = $data['statement_file'];
                    try {
                        $path = $this->uploadedFilePath($uploadedFile);
                        // Re-parse on submit so persisted data uses the same server-side validation as the preview.
                        $parsed = $parser->parse($path);
                        $normalized = $normalizer->normalize($parsed);
                        $batch = $service->createPreview(
                            $data['bank_code'],
                            FinancialAccount::query()->findOrFail($data['financial_account_id']),
                            auth()->user(),
                            $normalized,
                        );
                        Notification::make()
                            ->title('Statement preview saved')
                            ->body('Import batch #'.$batch->id.' is ready for review. No ledger transactions were posted.')
                            ->success()
                            ->send();
                    } catch (BankStatementParseException $exception) {
                        Notification::make()->title('Statement could not be parsed')->body('Validation code: '.$exception->errorCode)->danger()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Statement preview was not saved')->body('No import batch or ledger transaction was created. Review the validation findings and try again if appropriate.')->danger()->send();
                    } finally {
                        if ($uploadedFile instanceof TemporaryUploadedFile) {
                            $uploadedFile->delete();
                        }
                    }
                }),
        ];
    }

    private function uploadedFilePath(mixed $uploadedFile): string
    {
        if (! $uploadedFile instanceof TemporaryUploadedFile) {
            throw new \InvalidArgumentException('A temporary statement upload is required.');
        }

        return $uploadedFile->getRealPath();
    }

    private function refreshPreview(
        Get $get,
        Set $set,
        IngStatementPdfParser $parser,
        IngStatementTransactionNormalizer $normalizer,
        BankStatementImportBatchService $service,
    ): void {
        $uploadedFile = $get('statement_file');
        $accountId = $get('financial_account_id');
        if (! $uploadedFile instanceof TemporaryUploadedFile) {
            $set('preview_summary', 'Choose an account and upload a statement to inspect the parsed rows before saving a batch.');

            return;
        }

        try {
            $parsed = $parser->parse($uploadedFile->getRealPath());
            $normalized = $normalizer->normalize($parsed);
            $account = $accountId ? FinancialAccount::query()->find($accountId) : null;
            $diagnostics = $account ? $service->previewDiagnostics($account, $normalized) : [
                'requires_review' => true,
                'potential_duplicate_line_count' => 0,
                'duplicate_file' => false,
                'findings' => ['financial_account_required'],
            ];
            if ($account && strtoupper((string) $account->currency) !== $normalized->currency) {
                $diagnostics['requires_review'] = true;
                $diagnostics['findings'][] = 'account_currency_mismatch';
            }

            $lines = [
                'Statement period: '.$parsed->statementStart.' to '.$parsed->statementEnd,
                'Currency: '.$parsed->currency,
                'Opening balance: '.($parsed->openingBalance ?? 'unavailable'),
                'Closing balance: '.($parsed->closingBalance ?? 'unavailable'),
                'Total credits: '.($parsed->totalCredits ?? 'unavailable'),
                'Total debits: '.($parsed->totalDebits ?? 'unavailable'),
                'Parsed rows: '.count($normalized->transactions),
                'Review required: '.($diagnostics['requires_review'] ? 'yes' : 'no'),
                'Potential duplicate file: '.($diagnostics['duplicate_file'] ? 'yes' : 'no'),
                '',
                'ROWS (date | direction | amount | description | source page/row | review reasons)',
            ];
            foreach (array_slice($normalized->transactions, 0, 50) as $transaction) {
                $lines[] = implode(' | ', [
                    $transaction->transactionDate,
                    $transaction->direction,
                    $transaction->amount.' '.$transaction->currency,
                    $transaction->description,
                    $transaction->sourcePage.'/'.$transaction->sourceRow,
                    $transaction->reviewReasons === [] ? 'none' : implode(',', $transaction->reviewReasons),
                ]);
            }
            if (count($normalized->transactions) > 50) {
                $lines[] = 'Preview display capped at 50 rows; the full validated row count is shown above.';
            }
            if ($diagnostics['findings'] !== []) {
                $lines[] = '';
                $lines[] = 'VALIDATION FINDINGS';
                foreach ($diagnostics['findings'] as $finding) {
                    $lines[] = '- '.$finding;
                }
            }
            $lines[] = '';
            $lines[] = 'No import batch is saved until the confirmation checkbox is accepted and the form is submitted.';
            $lines[] = 'Ledger posting performed: no';
            $set('preview_summary', implode("\n", $lines));
        } catch (BankStatementParseException $exception) {
            $set('preview_summary', 'Validation failed: '.$exception->errorCode.'. This file cannot be saved as a valid import preview.');
        } catch (Throwable) {
            $set('preview_summary', 'Preview validation failed. No batch has been saved. Check the file, account selection, and supported layout.');
        }
    }
}
