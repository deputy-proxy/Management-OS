<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\CustomerLifecycleEvent;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LifecycleEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'lifecycleEvents';

    protected static ?string $title = 'Customer lifecycle events';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('event_type')
                ->label('Event')
                ->options([
                    CustomerLifecycleEvent::TYPE_CHURNED => 'Churned',
                    CustomerLifecycleEvent::TYPE_REACTIVATED => 'Reactivated',
                ])
                ->required(),
            DateTimePicker::make('effective_at')
                ->label('Effective at')
                ->default(now())
                ->required(),
            TextInput::make('reason')->maxLength(255)->required(),
            TextInput::make('evidence_reference')
                ->label('Evidence reference (restricted source locator)')
                ->maxLength(512)
                ->helperText('Store a controlled document or system reference, not sensitive personal details.')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_type')
            ->columns([
                TextColumn::make('event_type')->badge(),
                TextColumn::make('effective_at')->dateTime()->sortable(),
                TextColumn::make('reason')->wrap(),
                TextColumn::make('evidence_reference')->limit(50)->wrap(),
                TextColumn::make('recorder.name')->label('Recorded by')->placeholder('System'),
                TextColumn::make('recorded_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record lifecycle event')
                    ->visible(fn (): bool => hexa()->can('customer.update')),
            ]);
    }
}
