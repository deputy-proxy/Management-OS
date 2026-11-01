<?php

namespace App\Filament\Resources\CustomerLeads\Tables;

use App\Actions\ConvertCustomerLead;
use App\Actions\SendCustomerLeadContract;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use Throwable;

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
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('source_category')->label('Source category')->badge()->sortable(),
                Tables\Columns\TextColumn::make('source')->label('Source details')->searchable(),
                Tables\Columns\TextColumn::make('campaign_name')->toggleable(),
                Tables\Columns\TextColumn::make('topic_interest'),
                Tables\Columns\TextColumn::make('product_interest'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'new' => 'gray', 'contacted' => 'info', 'qualified' => 'success', 'proposal' => 'warning', 'converted' => 'success', 'lost' => 'danger', default => 'gray'
                }),
                Tables\Columns\TextColumn::make('last_contacted_at')->dateTime(),
                Tables\Columns\TextColumn::make('contract_send_status')->label('Contract delivery')->badge(),
                Tables\Columns\TextColumn::make('contract_sent_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options(CustomerLead::statuses()),
                SelectFilter::make('source_category')->label('Source category')->options(CustomerLead::sourceCategories()),
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
                Action::make('sendContract')
                    ->label('Send contract')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (CustomerLead $record): bool => ! $record->trashed()
                        && ! in_array($record->contract_send_status, ['queued', 'sent'], true)
                        && hexa()->can('customer_lead.update'))
                    ->authorize(fn (CustomerLead $record): bool => ! $record->trashed()
                        && ! in_array($record->contract_send_status, ['queued', 'sent'], true)
                        && hexa()->can('customer_lead.update'))
                    ->schema([
                        TextInput::make('recipient_preview')
                            ->label('Recipient email')
                            ->default(fn (CustomerLead $record): ?string => $record->email)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('document_preview')
                            ->label('Attached contract PDF')
                            ->default(fn (CustomerLead $record): ?string => $record->contract_document)
                            ->disabled()
                            ->dehydrated(false),
                        Checkbox::make('confirm_send')
                            ->label('I confirm this PDF should be emailed to the lead above.')
                            ->accepted()
                            ->required(),
                    ])
                    ->action(function (CustomerLead $record, array $data): void {
                        try {
                            $updatedLead = app(SendCustomerLeadContract::class)->handle($record);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Contract was not sent')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()
                                ->title('Contract delivery failed')
                                ->body('The delivery could not be queued. Check the delivery error on this lead before retrying.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($updatedLead->contract_send_status === 'sent' ? 'Contract sent' : 'Contract queued for delivery')
                            ->body($updatedLead->contract_send_status === 'sent'
                                ? 'The configured mail transport accepted the contract email.'
                                : 'Delivery status will update after the queued mail job runs.')
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
