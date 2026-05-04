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
                            'authorization' => 'Authorization',
                        ],
                        'Proof of fact or status' => [
                            'certificate' => 'Certificate',
                        ],
                        'Legal permission for a specific activity' => [
                            'permit' => 'Permit',
                        ],
                    ]),
                Forms\Components\DatePicker::make('expiration_date')
                    ->required(),
                Forms\Components\TextInput::make('physical_location')
                    ->required(),
                FileUpload::make('document')
                    ->preserveFilenames()
                    ->downloadable()
            ]);
    }
}
