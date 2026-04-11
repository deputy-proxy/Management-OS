<?php

namespace App\Filament\Resources\VendorContracts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
class VendorContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name'),
                Forms\Components\TextInput::make('contract_code'),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->required(),
                FileUpload::make('document')
                    ->required()
                    ->downloadable()
            ]);
    }
}
