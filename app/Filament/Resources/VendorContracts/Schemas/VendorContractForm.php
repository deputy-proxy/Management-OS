<?php
namespace App\Filament\Resources\VendorContracts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\VendorContract;

class VendorContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contract Reference')
                    ->description('The vendor and unique code identifying this contract.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    $set('contract_code', null);
                                    return;
                                }

                                $vendor = \App\Models\Vendor::find($state);

                                if (!$vendor) {
                                    return;
                                }

                                $contractCount = VendorContract::where('vendor_id', $state)->count() + 1;
                                $set('contract_code', $vendor->name . '-Contract ' . str_pad($contractCount, 2, '0', STR_PAD_LEFT));
                            }),

                        Forms\Components\TextInput::make('contract_code')
                            ->readOnly()
                            ->placeholder('Auto-generated on vendor selection'),
                    ]),

                Section::make('Contract Period')
                    ->description('The start and end dates defining the validity of this contract.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->required(),
                    ]),

                Section::make('Documentation')
                    ->description('The signed contract document attached to this record.')
                    ->columns(1)
                    ->schema([
                        FileUpload::make('document')
                            ->required()
                            ->downloadable(),
                    ]),
            ]);
    }
}