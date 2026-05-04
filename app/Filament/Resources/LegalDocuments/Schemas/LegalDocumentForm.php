<?php

namespace App\Filament\Resources\LegalDocuments\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;


class LegalDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Select::make('category')
                    ->required()
                    ->options([
                        'Third party permission' => [
                            'Authorization' => 'Authorization',
                        ],
                        'Proof of fact or status' => [
                            'Certificate' => 'Certificate',
                        ],
                        'Legal permission for a specific activity' => [
                            'Permit' => 'Permit',
                        ],
                    ]),
                Forms\Components\DatePicker::make('expiration_date')
                    ->required(),
                Select::make('physical_location')
                    ->required()
                    ->options([
                        'Office' => 'Office',
                        'Warehouse' => 'Warehouse',
                        'Other' => 'Other location',
                    ]),
                FileUpload::make('document')
                    ->preserveFilenames()
                    ->downloadable()
            ]);
    }
}
