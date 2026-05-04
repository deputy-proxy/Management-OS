<?php
namespace App\Filament\Resources\LegalDocuments\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;

class LegalDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Document Details')
                    ->description('Basic information about this legal document.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->columnSpanFull(),
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
                    ])
                    ->columns(2),

                Section::make('Storage')
                    ->description('Where this document is kept and its digital copy.')
                    ->schema([
                        Select::make('physical_location')
                            ->required()
                            ->options([
                                'Office'     => 'Office',
                                'Warehouse'  => 'Warehouse',
                                'Other'      => 'Other location',
                            ]),
                        FileUpload::make('document')
                            ->preserveFilenames()
                            ->downloadable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

            ]);
    }
}