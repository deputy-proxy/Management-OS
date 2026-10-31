<?php

namespace App\Filament\Resources\ProductResourceAllocations\Schemas;

use App\Models\PaidCampaign;
use App\Models\Product;
use App\Models\Project;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductResourceAllocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('product_resource_id')
                    ->relationship('productResource', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('targetable_type')
                    ->label('Attribution target type')
                    ->options([
                        'product' => 'Product',
                        'project' => 'Project',
                        'paid_campaign' => 'Paid campaign',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('targetable_id', null)),
                Forms\Components\Select::make('targetable_id')
                    ->label('Attribution target')
                    ->required()
                    ->searchable()
                    ->options(function (Get $get): array {
                        return match ($get('targetable_type')) {
                            'product' => Product::query()->orderBy('title')->pluck('title', 'id')->all(),
                            'project' => Project::query()->orderBy('title')->pluck('title', 'id')->all(),
                            'paid_campaign' => PaidCampaign::query()->orderBy('title')->pluck('title', 'id')->all(),
                            default => [],
                        };
                    }),
                Forms\Components\DatePicker::make('month')
                    ->required()
                    ->default(fn () => now()->startOfMonth())
                    ->helperText('Legacy period field retained for compatibility; effective dates select the rule for a source period.'),
                Forms\Components\Select::make('allocation_method')
                    ->label('Allocation method')
                    ->options(['percentage' => 'Percentage'])
                    ->default('percentage')
                    ->required(),
                Forms\Components\DatePicker::make('effective_from')
                    ->required()
                    ->default(fn (Get $get) => $get('month') ?: now()->startOfMonth()),
                Forms\Components\DatePicker::make('effective_until')
                    ->afterOrEqual('effective_from')
                    ->nullable(),
                Forms\Components\TextInput::make('allocation_percent')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),
                Forms\Components\TextInput::make('currency_code')
                    ->label('Expected source currency (optional)')
                    ->length(3)
                    ->maxLength(3)
                    ->dehydrateStateUsing(fn ($state) => $state === null ? null : strtoupper($state)),
            ]);
    }
}
