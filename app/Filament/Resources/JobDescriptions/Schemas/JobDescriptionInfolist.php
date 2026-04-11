<?php

namespace App\Filament\Resources\JobDescriptions\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class JobDescriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('job_title'),
                \Filament\Infolists\Components\TextEntry::make('job_level'),
                \Filament\Infolists\Components\TextEntry::make('job_role'),
                \Filament\Infolists\Components\KeyValueEntry::make('relevant_indicators'),
                \Filament\Infolists\Components\TextEntry::make('document_code'),
                \Filament\Infolists\Components\TextEntry::make('document'),
            ]);
    }
}
