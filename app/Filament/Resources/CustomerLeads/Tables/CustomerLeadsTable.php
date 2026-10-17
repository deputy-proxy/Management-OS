<?php

namespace App\Filament\Resources\CustomerLeads\Tables;

use App\Actions\ConvertCustomerLead;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Models\CustomerLead;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class CustomerLeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('middle_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('source'),
                Tables\Columns\TextColumn::make('topic_interest'),
                Tables\Columns\TextColumn::make('product_interest'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('last_contacted_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('turnIntoCustomer')
                    ->label('Turn into Customer')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (CustomerLead $record): bool => ! $record->converted_at
                        && $record->status !== 'converted'
                        && ! $record->trashed()
                        && hexa()->can('customer_lead.update')
                        && hexa()->can('customer.create')
                        && hexa()->can('customer_contract.create'))
                    ->authorize(fn (CustomerLead $record): bool => ! $record->converted_at
                        && $record->status !== 'converted'
                        && ! $record->trashed()
                        && hexa()->can('customer_lead.update')
                        && hexa()->can('customer.create')
                        && hexa()->can('customer_contract.create'))
                    ->schema([
                        Select::make('customer_type')
                            ->label('Customer type')
                            ->options([
                                'Individual' => 'Individual',
                                'Company' => 'Company',
                            ])
                            ->required(),
                        TextInput::make('cnp_cui')
                            ->label('CNP/CUI')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contract_code')
                            ->required()
                            ->maxLength(255)
                            ->rules([Rule::unique('customer_contracts', 'contract_code')]),
                        DatePicker::make('start_date')
                            ->required(),
                        DatePicker::make('end_date')
                            ->required()
                            ->afterOrEqual('start_date'),
                        FileUpload::make('document')
                            ->label('Signed contract document')
                            ->disk('public')
                            ->directory('customer-contracts')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable()
                            ->required(),
                        Checkbox::make('confirm_signed')
                            ->label('I confirm this uploaded document is the signed contract.')
                            ->accepted()
                            ->required(),
                    ])
                    ->action(function (CustomerLead $record, array $data): void {
                        $customer = app(ConvertCustomerLead::class)->handle($record, $data);

                        Notification::make()
                            ->title('Lead converted into customer')
                            ->body("Created customer {$customer->name} and its signed contract.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                CommentsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}