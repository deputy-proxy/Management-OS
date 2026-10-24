<?php

namespace App\Filament\Resources\BankStatementImportBatches;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\BankStatementImportBatches\Pages\ListBankStatementImportBatches;
use App\Filament\Resources\BankStatementImportBatches\Pages\ViewBankStatementImportBatch;
use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportEvent;
use App\Models\BankStatementImportItem;
use App\Services\BankStatements\BankStatementImportBatchService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BankStatementImportBatchResource extends Resource
{
    protected static ?string $model = BankStatementImportBatch::class;

    protected static ?string $navigationLabel = 'Statement Imports';

    protected static ?int $navigationSort = 10;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Treasurery & Tax';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-arrow-up';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('bank_code'),
            TextEntry::make('status'),
            TextEntry::make('financialAccount.title')->label('Financial account'),
            TextEntry::make('source_sha256'),
            TextEntry::make('statement_start')->date(),
            TextEntry::make('statement_end')->date(),
            TextEntry::make('transaction_count'),
            TextEntry::make('review_required_count'),
            TextEntry::make('warning_count'),
            TextEntry::make('decision_reason'),
            TextEntry::make('preview_payload')
                ->formatStateUsing(static fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                ->columnSpanFull(),
            TextEntry::make('imported_items')
                ->state(fn (BankStatementImportBatch $record): string => $record->items()->orderBy('source_page')->orderBy('source_row')->get()->map(static function (BankStatementImportItem $item): array {
                    return ['date' => (string) $item->transaction_date, 'direction' => $item->direction, 'amount' => $item->amount_decimal, 'currency' => $item->currency_code, 'description' => $item->description, 'operation_reference' => $item->operation_reference, 'page' => $item->source_page, 'row' => $item->source_row, 'review_reasons' => $item->review_reasons];
                })->toJson(JSON_PRETTY_PRINT) ?: '[]')
                ->columnSpanFull(),
            TextEntry::make('audit_events')
                ->state(fn (BankStatementImportBatch $record): string => $record->events()->orderBy('created_at')->get()->map(static function (BankStatementImportEvent $event): array {
                    return ['event' => $event->event_type, 'actor_id' => $event->actor_id, 'from' => $event->from_status, 'to' => $event->to_status, 'reason_code' => $event->reason_code, 'created_at' => date(DATE_ATOM, strtotime((string) $event->created_at))];
                })->toJson(JSON_PRETTY_PRINT) ?: '[]')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('financialAccount.title')->label('Account')->searchable(),
            TextColumn::make('bank_code')->label('Bank'),
            TextColumn::make('statement_start')->date(),
            TextColumn::make('statement_end')->date(),
            TextColumn::make('transaction_count')->numeric(),
            TextColumn::make('warning_count')->numeric(),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->recordActions([
            ViewAction::make(),
            Action::make('approve')
                ->requiresConfirmation()
                ->visible(fn (BankStatementImportBatch $record): bool => $record->status === BankStatementImportBatch::STATUS_PREVIEWED)
                ->authorize(fn (): bool => hexa()->can('transaction.update'))
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->action(function (BankStatementImportBatch $record, array $data, BankStatementImportBatchService $service): void {
                    $service->approve($record->id, auth()->user(), $data['reason']);
                }),
            Action::make('completeIngestion')
                ->label('Complete ingestion')
                ->requiresConfirmation()
                ->visible(fn (BankStatementImportBatch $record): bool => $record->status === BankStatementImportBatch::STATUS_APPROVED)
                ->authorize(fn (): bool => hexa()->can('transaction.update'))
                ->schema([Textarea::make('reason')->label('Completion note')->required()->maxLength(500)])
                ->action(function (BankStatementImportBatch $record, array $data, BankStatementImportBatchService $service): void {
                    $service->complete($record->id, auth()->user(), $data['reason']);
                }),
            Action::make('markFailed')
                ->label('Mark failed')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (BankStatementImportBatch $record): bool => in_array($record->status, [BankStatementImportBatch::STATUS_PREVIEWED, BankStatementImportBatch::STATUS_APPROVED], true))
                ->authorize(fn (): bool => hexa()->can('transaction.update'))
                ->schema([
                    TextInput::make('reason_code')->required()->regex('/^[a-z0-9_]{3,64}$/')->maxLength(64),
                    Textarea::make('reason')->required()->maxLength(500),
                ])
                ->action(function (BankStatementImportBatch $record, array $data, BankStatementImportBatchService $service): void {
                    $service->markFailed($record->id, auth()->user(), $data['reason_code'], $data['reason']);
                }),
            Action::make('retry')
                ->label('Retry validation')
                ->visible(fn (BankStatementImportBatch $record): bool => $record->status === BankStatementImportBatch::STATUS_FAILED)
                ->authorize(fn (): bool => hexa()->can('transaction.update'))
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->action(function (BankStatementImportBatch $record, array $data, BankStatementImportBatchService $service): void {
                    $service->retry($record->id, auth()->user(), $data['reason']);
                }),
            Action::make('reject')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (BankStatementImportBatch $record): bool => $record->status === BankStatementImportBatch::STATUS_PREVIEWED)
                ->authorize(fn (): bool => hexa()->can('transaction.update'))
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->action(function (BankStatementImportBatch $record, array $data, BankStatementImportBatchService $service): void {
                    $service->reject($record->id, auth()->user(), $data['reason']);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankStatementImportBatches::route('/'),
            'view' => ViewBankStatementImportBatch::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('transaction.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('transaction.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Import batches cannot be edited directly.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Import batches and their audit history cannot be deleted.');
    }
}
