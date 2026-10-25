<?php

namespace App\Filament\Resources\BankStatementImportItems;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportItem;
use App\Models\CustomerInvoice;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use App\Services\BankStatements\BankStatementMatchingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BankStatementImportItemResource extends Resource
{
    protected static ?string $model = BankStatementImportItem::class;

    protected static ?string $navigationLabel = 'Statement Matching';

    protected static ?int $navigationSort = 11;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Treasurery & Tax';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('batch', fn ($query) => $query->where('status', BankStatementImportBatch::STATUS_COMPLETED));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('batch_id')->label('Import batch'),
            TextEntry::make('financialAccount.title')->label('Financial account'),
            TextEntry::make('transaction_date')->date(),
            TextEntry::make('direction'),
            TextEntry::make('amount_decimal')->label('Amount'),
            TextEntry::make('currency_code')->label('Currency'),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('operation_reference'),
            TextEntry::make('source_sha256')->label('Source SHA-256'),
            TextEntry::make('parser_version'),
            TextEntry::make('source_page'),
            TextEntry::make('source_row'),
            TextEntry::make('match_decision')->state(fn (BankStatementImportItem $record): string => app(BankStatementMatchingService::class)->currentDecision($record)?->properties?->toJson(JSON_PRETTY_PRINT) ?? 'No manual decision recorded')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('batch_id')->label('Batch')->sortable(),
            TextColumn::make('financialAccount.title')->label('Account')->searchable(),
            TextColumn::make('transaction_date')->date()->sortable(),
            TextColumn::make('direction')->badge(),
            TextColumn::make('amount_decimal')->label('Amount')->sortable(),
            TextColumn::make('currency_code')->label('Currency'),
            TextColumn::make('description')->limit(60)->searchable(),
            TextColumn::make('source_page')->label('Page'),
            TextColumn::make('source_row')->label('Row'),
            TextColumn::make('match_outcome')->label('Decision')->state(fn (BankStatementImportItem $record): string => (string) (app(BankStatementMatchingService::class)->currentDecision($record)?->getProperty('outcome') ?? 'unreviewed'))->badge(),
        ])->recordActions([
            Action::make('recordMatch')
                ->label('Review / match')
                ->schema([
                    Select::make('outcome')->options([
                        'exact_match' => 'Exact match',
                        'partial_candidate' => 'Partial candidate',
                        'unmatched' => 'Unmatched',
                        'ambiguous' => 'Ambiguous',
                        'rejected' => 'Rejected',
                    ])->required()->live(),
                    Select::make('target_type')->label('Target type')->options([
                        'transaction' => 'Existing transaction',
                        'customer_invoice' => 'Customer invoice',
                        'vendor_invoice' => 'Vendor invoice',
                    ])->visible(fn ($get): bool => in_array($get('outcome'), ['exact_match', 'partial_candidate'], true))->required(fn ($get): bool => in_array($get('outcome'), ['exact_match', 'partial_candidate'], true))->live(),
                    Select::make('target_id')->label('Target record')->options(function ($get, BankStatementImportItem $record): array {
                        $type = $get('target_type');

                        return match ($type) {
                            'transaction' => Transaction::query()->where('financial_account_id', $record->financial_account_id)->orderByDesc('id')->limit(100)->get()->mapWithKeys(fn (Transaction $target): array => [$target->id => '#'.$target->id.' '.$target->transaction_type.' '.$target->getAttribute('amount_decimal').' '.$target->getAttribute('currency_code')])->all(),
                            'customer_invoice' => CustomerInvoice::query()->orderByDesc('id')->limit(100)->get()->mapWithKeys(fn (CustomerInvoice $target): array => [$target->id => '#'.$target->id.' '.$target->identifier.' '.$target->getAttribute('amount_without_vat_decimal').' '.$target->getAttribute('currency_code')])->all(),
                            'vendor_invoice' => VendorInvoice::query()->orderByDesc('id')->limit(100)->get()->mapWithKeys(fn (VendorInvoice $target): array => [$target->id => '#'.$target->id.' '.$target->identifier.' '.$target->getAttribute('amount_decimal').' '.$target->getAttribute('currency_code')])->all(),
                            default => [],
                        };
                    })->searchable()->visible(fn ($get): bool => in_array($get('outcome'), ['exact_match', 'partial_candidate'], true))->required(fn ($get): bool => in_array($get('outcome'), ['exact_match', 'partial_candidate'], true)),
                    Textarea::make('reason')->label('Reason / evidence')->required()->minLength(1)->maxLength(500),
                ])
                ->action(function (BankStatementImportItem $record, array $data, BankStatementMatchingService $service): void {
                    $service->recordDecision(
                        $record->id,
                        auth()->user(),
                        $data['outcome'],
                        $data['target_type'] ?? null,
                        isset($data['target_id']) ? (int) $data['target_id'] : null,
                        $data['reason'],
                    );
                    Notification::make()->title('Matching decision recorded')->body('No settlement, allocation, or ledger mutation was performed.')->success()->send();
                })
                ->authorize(fn (): bool => hexa()->can('transaction.update')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankStatementImportItems::route('/'),
            'view' => Pages\ViewBankStatementImportItem::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('transaction.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('Statement rows are created only by the import workflow.');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Imported statement rows are immutable.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Imported statement rows and their provenance are immutable.');
    }
}
